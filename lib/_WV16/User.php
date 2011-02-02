<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_User extends WV_Object implements WV16_User {
	const ERR_UNKNOWN_USER  = 1;
	const ERR_INVALID_LOGIN = 2;
	const ERR_PWD_TOO_SHORT = 3;
	const ERR_LOGIN_EXISTS  = 4;

	protected $id;
	protected $login;
	protected $password;
	protected $typeID;
	protected $origTypeID;
	protected $registered;
	protected $values;
	protected $groups;
	protected $deleted;
	protected $activated;
	protected $confirmed;
	protected $wasActivated;
	protected $currentSetID;
	protected $confirmationCode;

	private static $instances = array();

	/**
	 * @return _WV16_User  der entsprechende Benutzer
	 */
	public static function getInstance($userID) {
		$userID = (int) $userID;

		if (empty(self::$instances[$userID])) {
			$callback = array(__CLASS__, '_getInstance');
			$instance = self::getFromCache('frontenduser.users', $userID, $callback, $userID);

			self::$instances[$userID] = $instance;
		}

		return self::$instances[$userID];
	}

	protected static function _getInstance($id) {
		return new self($id);
	}

	/**
	 * @return void
	 */
	private function __construct($id) {
		$sql  = WV_SQLEx::getInstance();
		$data = $sql->safeFetch('*', 'wv16_users', 'id = ?', $id);

		if (empty($data)) {
			throw new WV16_Exception('Der Benutzer #'.$id.' konnte nicht gefunden werden!', self::ERR_UNKNOWN_USER);
		}

		$this->id               = (int) $data['id'];
		$this->login            = $data['login'];
		$this->password         = $data['password'];
		$this->registered       = $data['registered'];
		$this->typeID           = (int) $data['type_id'];
		$this->origTypeID       = $this->typeID;
		$this->values           = null;
		$this->deleted          = (boolean) $data['deleted'];
		$this->activated        = (boolean) $data['activated'];
		$this->confirmed        = (boolean) $data['confirmed'];
		$this->wasActivated     = (boolean) $data['was_activated'];
		$this->currentSetID     = WV16_Users::getFirstSetID($this->id);
		$this->groups           = $sql->getArray('SELECT group_id FROM ~wv16_user_groups WHERE user_id = ?', $this->id, '~');
		$this->confirmationCode = $data['confirmation_code'];
	}

	/**
	 * @return _WV16_User  der neu erzeugte Benutzer
	 */
	public static function register($login, $password, $userType = null) {
		$sql      = WV_SQLEx::getInstance();
		$password = trim($password);
		$login    = trim($login);
		$userType = _WV16_FrontendUser::getIDForUserType($userType, true);

		if ($userType === null) {
			$userType = _WV16_FrontendUser::DEFAULT_USER_TYPE;
		}

		if (empty($login)) {
			throw new WV16_Exception('Der Login darf nicht leer sein.', self::ERR_INVALID_LOGIN);
		}

		if (!preg_match('#^[a-z0-9_.,;\#+-@]+$#i', $login)) {
			throw new WV16_Exception('Der Login enthält ungültige Zeichen.', self::ERR_INVALID_LOGIN);
		}

		if ($sql->count('wv16_users', 'LOWER(login) = ?', strtolower($login)) != 0) {
			throw new WV16_Exception('Der Login ist bereits vergeben.', self::ERR_LOGIN_EXISTS);
		}

		self::testPassword($password);

		$registered       = date('Y-m-d H:i:s');
		$confirmationCode = WV16_Users::generateConfirmationCode($login);
		$params           = array($login, $password, $userType, $registered, $confirmationCode);

		return self::transactionGuard(array(__CLASS__, '_register'), $params, 'WV16_Exception');
	}

	protected static function _register($login, $password, $userType, $registered, $confirmationCode) {
		$sql = WV_SQLEx::getInstance();

		$sql->queryEx(
			'INSERT INTO ~wv16_users (login,password,registered,type_id,activated,confirmed,confirmation_code) VALUES (?,"",?,?,?,?,?)',
			array($login, $registered, $userType, 0, 0, $confirmationCode), '~'
		);

		$userID = $sql->lastID();
		$pwhash = sha1($userID.$password.$registered);

		$sql->queryEx('UPDATE ~wv16_users SET password = ? WHERE id = ?', array($pwhash, $userID), '~');

		// Attribute und ihre Standardwerte übernehmen

		$attributes = WV16_Users::getAttributesForUserType($userType);

		foreach ($attributes as $attr) {
			$sql->queryEx(
				'INSERT INTO ~wv16_user_values (user_id,attribute_id,set_id,value) VALUES (?,?,?,?)',
				array($userID, $attr->getID(), 1, $attr->getDefault()), '~'
			);
		}

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.counts', true);
		$cache->flush('frontenduser.lists', true);

		return self::getInstance($userID);
	}

	/**
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function update() {
		return self::transactionGuard(array($this, '_update'), null, 'WV16_Exception');
	}

	protected function _update() {
		$sql = WV_SQLEx::getInstance();

		if ($sql->count('wv16_users','LOWER(login) = ? AND id <> ?', array(strtolower($this->login), $this->id)) != 0) {
			throw new WV16_Exception('Der Login ist bereits vergeben.', self::ERR_LOGIN_EXISTS);
		}

		if ($this->confirmationCode === null) {
			if ($this->confirmed) {
				$this->confirmationCode = '';
			}
			else {
				$this->confirmationCode = WV16_Users::generateConfirmationCode($this->login);
			}
		}

		$sql->queryEx(
			'UPDATE ~wv16_users SET login = ?, password = ?, type_id = ?, activated = ?, confirmed = ?, confirmation_code = ? WHERE id = ?',
			array($this->login, $this->password, $this->typeID, (int) $this->activated, (int) $this->confirmed, $this->confirmationCode, $this->id), '~'
		);

		if ($this->activated === true && $this->wasActivated === false) {
			$sql->queryEx('UPDATE ~wv16_users SET was_activated = 1 WHERE id = ?', $this->id, '~');
			$this->wasActivated = true;
		}

		if ($this->typeID != $this->origTypeID) {
			$oldTypesAttributes = WV16_Users::getAttributesForUserType($this->origTypeID);
			$newTypesAttributes = WV16_Users::getAttributesForUserType($this->typeID);

			foreach ($oldTypesAttributes as $idx => $attr) $oldTypesAttributes[$idx] = $attr->getID();
			foreach ($newTypesAttributes as $idx => $attr) $newTypesAttributes[$idx] = $attr->getID();

			$toDelete = array_diff($oldTypesAttributes, $newTypesAttributes);
			$toAdd    = array_diff($newTypesAttributes, $oldTypesAttributes);

			if (!empty($toDelete)) {
				$markers = implode(',', $toDelete);

				$sql->queryEx(
					'DELETE FROM ~wv16_user_values WHERE user_id = ? AND attribute_id IN ('.$markers.')',
					$this->id, '~'
				);
			}

			if (!empty($toAdd)) {
				$markers = implode(',', $toAdd);

				$sql->queryEx(
					'INSERT INTO ~wv16_user_values (user_id,attribute_id,value) '.
					'SELECT ?,id,default_value FROM ~wv16_attributes WHERE id IN ('.$markers.')',
					$this->id, '~'
				);
			}
		}

		if ($this->typeID != $this->origTypeID) {
			$this->values     = null; // neues Abrufen beim Aufruf von getAttributes() veranlassen
			$this->origTypeID = $this->typeID;
		}

		$cache = sly_Core::cache();
		$cache->delete('frontenduser.users', $this->id);
		$cache->delete('frontenduser.users.firstsets', $this->id);
		$cache->delete('frontenduser.users.typeids', $this->id);
		$cache->flush('frontenduser.lists', true);
		$cache->flush('frontenduser.counts', true);

		return true;
	}

	/**
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function delete() {
		return self::transactionGuard(array($this, '_delete'), null, 'WV16_Exception');
	}

	protected function _delete() {
		$sql = WV_SQLEx::getInstance();

		$sql->queryEx('DELETE FROM ~wv16_users WHERE id = ?', $this->id, '~');
		$sql->queryEx('DELETE FROM ~wv16_user_groups WHERE user_id = ?', $this->id, '~');
		$sql->queryEx('DELETE FROM ~wv16_user_values WHERE user_id = ?', $this->id, '~');

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.uservalues', true);
		$cache->flush('frontenduser.lists', true);
		$cache->flush('frontenduser.counts', true);

		return true;
	}

	/**
	 * @return boolean  true, falls ja, sonst false
	 */
	public static function exists($login) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.users';
		$cacheKey  = sly_Cache::generateKey('mapping', $login);

		if ($cache->exists($namespace, $cacheKey)) {
			return true;
		}

		$sql = WV_SQLEx::getInstance();
		$id  = $sql->safeFetch('id', 'wv16_users','LOWER(login) = ?', strtolower($login));

		if ($id !== false) {
			$cache->set($namespace, $cacheKey, (int) $id);
			return true;
		}

		return false;
	}

	public function getLogin()            { return $this->login;            }
	public function getID()               { return $this->id;               }
	public function getRegistered()       { return $this->registered;       }
	public function getPasswordHash()     { return $this->password;         }
	public function getTypeID()           { return $this->typeID;           }
	public function getGroupIDs()         { return $this->groups;           }
	public function isActivated()         { return $this->activated;        }
	public function isConfirmed()         { return $this->confirmed;        }
	public function getConfirmationCode() { return $this->confirmationCode; }

	/**
	 * @return _WV16_UserType  der Benutzertyp als Objekt
	 */
	public function getType() {
		return _WV16_UserType::getInstance($this->typeID);
	}

	/**
	 * @return array  Liste aller Gruppen als Objekte
	 */
	public function getGroups() {
		$obj = array();

		foreach ($this->groups as $id) {
			$obj[] = _WV16_Group::getInstance($id);
		}

		return $obj;
	}

	/**
	 * @return _WV16_UserValue  der Benutzerwert
	 */
	public function getValue($attribute, $default = null) {
		$this->getValues();

		$id = _WV16_FrontendUser::getIDForAttribute($attribute);
		return isset($this->values[$id]) ? $this->values[$id] : new _WV16_UserValue($default, null, null, null);
	}

	/**
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function setValue($attribute, $value) {
		$retval = WV16_Users::setDataForUser($this, $attribute, $value);

		if ($retval) {
			$cache = sly_Core::cache();
			$cache->delete('frontenduser.users', $this->id);
			$cache->delete('frontenduser.users.firstsets', $this->id);
			$cache->flush('frontenduser.lists', true);
			$this->values = null;
		}

		return $retval;
	}

	public function getValues() {
		if ($this->values === null) {
			$this->values = WV16_Users::getDataForUser($this->id, null, null, $this->currentSetID);

			if ($this->values === null) {
				$this->values = array();
			}
			elseif (!is_array($this->values)) {
				$attr = $this->values;
				unset($this->values);
				$this->values[$attr->getAttributeName()] = $attr;
			}

			// von assoziativ auf normal-indiziert (IDs umschalten)

			$a = array();

			foreach ($this->values as $name => $attr) {
				$a[$attr->getAttributeID()] = $attr;
			}

			$this->values = null; // Speicher freigeben
			$this->values = $a;

			// Benutzer neu cachen

			$cache     = sly_Core::cache();
			$namespace = 'frontenduser.users';

			$cache->set($namespace, $this->id, $this);
		}

		return $this->values;
	}

	public function isInGroup($group) {
		$group = _WV16_FrontendUser::getIDForGroup($group, false);
		return array_search($group, $this->groups) !== false;
	}

	public function addGroup($group) {
		$group = _WV16_FrontendUser::getIDForGroup($group, false);
		return self::transactionGuard(array($this, '_addGroup'), $group, 'WV16_Exception');
	}

	protected function _addGroup($group) {
		$sql = WV_SQLEx::getInstance();

		if (_WV16_Group::exists($group)) {
			if ($sql->count('wv16_user_groups', 'user_id = ? AND group_id = ?', array($this->id, $group)) == 0) {
				$sql->queryEx('INSERT INTO ~wv16_user_groups (user_id,group_id) VALUES (?,?)', array($this->id, $group), '~');
				$this->groups[] = $group;
			}
		}

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.lists', true);

		return true;
	}

	public function wasEverActivated() {
		return $this->wasActivated;
	}

	public function wasNeverActivated() {
		return !$this->wasActivated;
	}

	public function removeGroup($group) {
		$group = _WV16_FrontendUser::getIDForGroup($group, false);
		return self::transactionGuard(array($this, '_removeGroup'), $group, 'WV16_Exception');
	}

	protected function _removeGroup($group) {
		$index = $this->groups === null ? false : array_search($group, $this->groups);
		$sql   = WV_SQLEx::getInstance();
		$query = 'DELETE FROM ~wv16_user_groups WHERE user_id = ? AND group_id = ?';

		$sql->queryEx($query, array($this->id, $group), '~');

		if ($index !== false) {
			unset($this->groups[$index]);
		}

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.lists', true);

		return true;
	}

	public function removeAllGroups() {
		return self::transactionGuard(array($this, '_removeAllGroups'), null, 'WV16_Exception');
	}

	protected function _removeAllGroups() {
		$sql = WV_SQLEx::getInstance();

		$sql->queryEx('DELETE FROM ~wv16_user_groups WHERE user_id = ?', $this->id, '~');
		$this->groups = array();

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.lists', true);

		return true;
	}

	public function setConfirmationCode($code = null) {
		$code = $code === null ? WV16_Users::generateConfirmationCode($this->login) : substr($code, 0, 20);
		$this->confirmationCode = $code;
		return $code;
	}

	public function setConfirmed($isConfirmed = true, $confirmationCode = null) {
		// Auf Wunsch kann auch diese Methode die Überprüfung auf den
		// Bestätigungscode selbst durchführen.

		if (is_string($confirmationCode)) {
			$isConfirmed = $this->confirmationCode == $confirmationCode;
		}

		$this->confirmed        = $isConfirmed;
		$this->confirmationCode = null;
	}

	public function setActivated($isActivated = true) {
		$this->activated = (boolean) $isActivated;
	}

	public function setLogin($login) {
		// Benutzer, die mit Daten befüllt sind, die aus read-only Sets kommen, können sich nicht ändern.

		if ($this->isReadOnly()) {
			return false;
		}

		$login = trim($login);

		if (!preg_match('#^[a-z0-9_.,;\#+-@]+$#i', $login)) {
			throw new WV16_Exception('Der Login enthält ungültige Zeichen.', self::ERR_INVALID_LOGIN);
		}

		$this->login = $login;
	}

	public function setPassword($password, $passwordRepeat = null) {
		// Benutzer, die mit Daten befüllt sind, die aus read-only Sets kommen, können sich nicht ändern.

		if ($this->isReadOnly()) {
			return false;
		}

		$password = trim($password);
		self::testPassword($password);

		if ($passwordRepeat !== null && $password != trim($passwordRepeat)) {
			return false;
		}

		$this->password = sha1($this->id.$password.$this->registered);
		return true;
	}

	public static function testPassword($password) {
		if (strlen($password) < 6) {
			throw new WV16_Exception('Das Passwort ist zu kurz (mindestens 6 Zeichen!)', self::ERR_PWD_TOO_SHORT);
		}

		// Besteht das Passwort nur aus Zahlen?

		if (preg_match('#^[0-9]$#', $password)) {
			throw new WV16_Exception('Das Passwort ist anfällig gegenüber Wörterbuch-Angriffen!');
		}

		// TODO: Hier genauere und im Backend konfigurierbare Testroutine einbauen.
		// Entsprechende Implementierungen finden sich in der class.securepwd.php.

		return true;
	}

	public function setUserType($userType) {
		// Benutzer, die mit Daten befüllt sind, die aus read-only Sets kommen, können sich nicht ändern.

		if ($this->isReadOnly()) {
			return false;
		}

		$userType = _WV16_FrontendUser::getIDForUserType($userType, false);

		if (_WV16_UserType::exists($userType)) {
			$this->typeID = $userType;
		}
	}

	public function setSetID($setID) {
		$setID = (int) $setID;
		$sql   = WV_SQLEx::getInstance();

		if ($sql->count('wv16_user_values', 'user_id = ? AND set_id = ?', array($this->id, $setID)) > 0) {
			$this->currentSetID = $setID;
			$this->values       = null;
			$this->getValues();
			return true;
		}

		return false;
	}

	public function getSetID() {
		return $this->currentSetID;
	}

	public function isDeleted() {
		return $this->deleted;
	}

	public function getSetIDs($includeReadOnly = false) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('set_ids', $this->id, $includeReadOnly);

		$ids = $cache->get($namespace, $cacheKey, null);

		if (is_array($ids)) {
			return $ids;
		}

		$includeReadOnly = $includeReadOnly ? '' : ' AND set_id >= 0';

		$ids = WV_SQLEx::getInstance()->getArray(
			'SELECT DISTINCT set_id FROM ~wv16_user_values WHERE user_id = ?'.$includeReadOnly.' ORDER BY set_id',
			$this->id, '~'
		);

		$ids = array_map('intval', $ids);
		$cache->set($namespace, $cacheKey, $ids);
		return $ids;
	}

	public function createSetCopy($sourceSetID = null) {
		$setID = $sourceSetID === null ? WV16_Users::getFirstSetID($this->id) : (int) $sourceSetID;
		$newID = WV_SQLEx::getInstance()->safeFetch('MAX(set_id)', 'wv16_user_values', 'user_id = ?', $this->id) + 1;

		$this->copySet($setID, $newID);

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.lists', true);
		$cache->delete('frontenduser.users.firstsets', $this->id);
		return $newID;
	}

	public function createReadOnlySet($sourceSetID = null) {
		$setID = $sourceSetID === null ? WV16_Users::getFirstSetID($this->id) : (int) $sourceSetID;
		$newID = WV_SQLEx::getInstance()->safeFetch('MIN(set_id)', 'wv16_user_values', 'user_id = ?', $this->id) - 1;

		// Ab Version 1.2.1 sind die Standard-IDs >= 0. Um Konflikten aus dem Weg zu gehen, wenn alte
		// Daten aktualisiert werden, stellen wir hier sicher, dass die erste ReadOnly-ID garantiert
		// kleiner als 0 ist.

		if ($newID == 0) {
			$newID = -1;
		}

		$this->copySet($setID, $newID);

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.lists', true);
		$cache->delete('frontenduser.users.firstsets', $this->id);
		return $newID;
	}

	public function deleteSet($setID = null) {
		$setID = $setID === null ? WV16_Users::getFirstSetID($this->id) : (int) $setID;

		if (self::isReadOnlySet($setID)) {
			throw new WV16_Exception('Schreibgeschützte Sets können nicht gelöscht werden.');
		}

		$sql    = WV_SQLEx::getInstance();
		$params = array($this->id, $setID);

		$sql->queryEx('DELETE FROM ~wv16_user_values WHERE user_id = ? AND set_id = ?', $params, '~');

		$cache = sly_Core::cache();
		$cache->delete('frontenduser.users', $this->id);
		$cache->delete('frontenduser.users.firstsets', $this->id);
		$cache->flush('frontenduser.lists', true);
		$cache->flush('frontenduser.counts', true);

		return $sql->affectedRows() > 0; // ein Set kann mehr als einen Wert enthalten
	}

	protected function copySet($sourceSet, $targetSet) {
		return WV_SQLEx::getInstance()->queryEx(
			'INSERT INTO ~wv16_user_values '.
			'SELECT user_id,attribute_id,?,value FROM ~wv16_user_values WHERE user_id = ? AND set_id = ?',
			array($targetSet, $this->id, $sourceSet), '~', WV_SQLEx::RETURN_FALSE
		);
	}

	public function isReadOnly() {
		return self::isReadOnlySet($this->currentSetID);
	}

	public static function isReadOnlySet($setID) {
		return $setID < 0;
	}
}
