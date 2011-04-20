<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_User extends WV_Object implements WV16_User {
	protected $id;
	protected $login;
	protected $password;
	protected $type;
	protected $registered;
	protected $rawValues;
	protected $values;
	protected $groups;
	protected $deleted;
	protected $activated;
	protected $confirmed;
	protected $wasActivated;
	protected $currentSetID;
	protected $confirmationCode;

	private static $instances = array();

	public function __sleep() {
		return array('id', 'login', 'password', 'type', 'registered', 'rawValues',
			'groups', 'deleted', 'activated', 'confirmed', 'wasActivated',
			'currentSetID', 'confirmationCode'
		);
	}

	public function __wakeup() {
		$this->values = null;
	}

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

	private function __construct($id) {
		$sql  = WV_SQL::getInstance();
		$data = $sql->fetch('*', 'wv16_users', 'id = ?', $id);

		if (empty($data)) {
			throw new WV16_Exception('Der Benutzer #'.$id.' konnte nicht gefunden werden!', self::ERR_UNKNOWN_USER);
		}

		$this->id               = (int) $data['id'];
		$this->login            = $data['login'];
		$this->password         = $data['password'];
		$this->registered       = $data['registered'];
		$this->type             = $data['type'];
		$this->rawValues        = null;
		$this->values           = null;
		$this->deleted          = (boolean) $data['deleted'];
		$this->activated        = (boolean) $data['activated'];
		$this->confirmed        = (boolean) $data['confirmed'];
		$this->wasActivated     = (boolean) $data['was_activated'];
		$this->currentSetID     = $this->getFirstSetID();
		$this->groups           = $sql->getArray('SELECT `group` FROM ~wv16_user_groups WHERE user_id = ?', $this->id, '~');
		$this->confirmationCode = $data['confirmation_code'];
	}

	/**
	 * @return _WV16_User  der neu erzeugte Benutzer
	 */
	public static function register($login, $password, $userType = null) {
		$service = new _WV16_Service_User();
		$userID  = $service->register($login, $password, $userType);

		return self::getInstance($userID);
	}

	public function update() {
		$service = new _WV16_Service_User();
		$service->update($this);

		$this->values = null;
	}

	public function delete() {
		$service = new _WV16_Service_User();
		$service->delete($this);
	}

	/**
	 * @return boolean  true, falls ja, sonst false
	 */
	public static function exists($login) {
		$service = new _WV16_Service_User();
		return $service->exists($login);
	}

	public function checkPassword($password) {
		$password = trim($password);
		return sha1($this->id.$password.$this->registered) === $this->password;
	}

	/**
	 * Prüft, ob ein Benutzer von einem bestimmten Typ ist
	 *
	 * @param  string $userType  der Name des Benutzertyps
	 * @return bool              true oder false
	 */
	public function isOfType($userType) {
		return $userType === $this->type;
	}

	public function getLogin()            { return $this->login;            }
	public function getID()               { return $this->id;               }
	public function getRegistered()       { return $this->registered;       }
	public function getPasswordHash()     { return $this->password;         }
	public function getTypeName()         { return $this->type;             }
	public function getGroupIDs()         { return $this->groups;           }
	public function isActivated()         { return $this->activated;        }
	public function isConfirmed()         { return $this->confirmed;        }
	public function getConfirmationCode() { return $this->confirmationCode; }
	public function getSetID()            { return $this->currentSetID;     }
	public function isDeleted()           { return $this->deleted;          }
	public function wasEverActivated()    { return $this->wasActivated;     }
	public function wasNeverActivated()   { return !$this->wasActivated;    }

	/**
	 * @return _WV16_UserType  der Benutzertyp als Objekt
	 */
	public function getType() {
		return _WV16_UserType::getInstance($this->type);
	}

	/**
	 * @return array  Liste aller Gruppen als Objekte
	 */
	public function getGroups() {
		$return = array();

		foreach ($this->groups as $name) {
			$return[$name] = _WV16_Group::getInstance($name);
		}

		return $return;
	}

	/**
	 * @return mixed  der Benutzerwert
	 */
	public function getValue($attribute, $default = null) {
		$values = $this->getValues();
		return isset($values[$attribute]) ? $values[$attribute] : $default;
	}

	/**
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function setValue($attribute, $value) {
		$service = new _WV16_Service_Value();
		$written = $service->write($this, null, $attribute, $value);

		if ($written) {
			$cache = sly_Core::cache();
			$cache->delete('frontenduser.users', $this->id);
			$cache->delete('frontenduser.users.firstsets', $this->id);
			$cache->flush('frontenduser.lists', true);

			$this->rawValues = null;
			$this->values    = null;
		}

		return $written;
	}

	public function getValues($raw = false) {
		// Wurden die Werte noch nicht abgerufen?

		if ($this->rawValues === null) {
			$service         = new _WV16_Service_Value();
			$this->rawValues = $service->read($this, null, true);

			// Benutzer neu cachen

			$cache     = sly_Core::cache();
			$namespace = 'frontenduser.users';

			$cache->set($namespace, $this->id, $this);
		}

		// Rohdaten zurückgeben?

		if ($raw) {
			return $this->rawValues;
		}

		// Werte deserialisieren

		if (!is_array($this->values)) {
			$this->values = array();
		}

		foreach ($this->rawValues as $attr => $rawValue) {
			if (!isset($this->values[$attr])) {
				$this->values[$attr] = WV16_Factory::getAttribute($attr)->deserialize($rawValue);
			}
		}

		return $this->values;
	}

	/**
	 * Prüfen, ob Wert vorhanden ist
	 *
	 * Diese Methode prüft, ob der Benutzer einen bestimmten Wert besitzt.
	 *
	 * @param  string $attribute  das Attribut
	 * @param  mixed  $value      der gesuchte Wert
	 * @return boolean            true, wenn der Benutzer den gesuchte Wert bestitzt, sonst false
	 */
	public function hasValue($attribute, $value) {
		$value = $this->getValue($attribute);

		if ($data === null) {
			return false;
		}

		if (!is_array($v)) {
			return $value == $v;
		}

		return in_array($value, array_keys($v)) || in_array($value, array_values($v));
	}


	/* Gruppen-Management */


	public function isInGroup($group) {
		return array_search($group, $this->groups) !== false;
	}

	public function addGroup($group) {
		$service = new _WV16_Service_User();

		if ($service->addToGroup($this, $group)) {
			$this->groups[] = $group;
			return true;
		}

		return false;
	}

	public function removeGroup($group) {
		$service = new _WV16_Service_User();
		$service->removeFromGroup($this, $group);

		$index = array_search($group, $this->groups);

		if ($index !== false) {
			unset($this->groups[$index]);
		}

		return true;
	}

	public function removeAllGroups() {
		$service = new _WV16_Service_User();
		return $service->removeFromAllGroups($this);
	}

	public function setConfirmationCode($code = null) {
		$code = $code === null ? WV16_Users::generateConfirmationCode($this->login) : substr($code, 0, 20);
		$this->confirmationCode = $code;
		return $code;
	}


	/* Setter */


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
		if ($this->isReadOnly()) return false;
		$this->login = trim($login);
	}

	public function setPassword($password, $passwordRepeat = null) {
		if ($this->isReadOnly()) return false;

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
			throw new WV16_Exception('Das Passwort ist zu kurz (mindestens 6 Zeichen!)');
		}

		// Besteht das Passwort nur aus Zahlen?

		if (preg_match('#^[0-9]$#', $password)) {
			throw new WV16_Exception('Das Passwort ist anfällig gegenüber Wörterbuch-Angriffen!');
		}

		return true;
	}

	public function setUserType($userType) {
		if ($this->isReadOnly()) return false;

		if (_WV16_UserType::exists($userType)) {
			$this->type = $userType;
		}
	}


	/* Set-Management */


	public function setSetID($setID) {
		$setID = (int) $setID;
		$sql   = WV_SQL::getInstance();

		if ($sql->count('wv16_user_values', 'user_id = ? AND set_id = ?', array($this->id, $setID)) > 0) {
			$this->currentSetID = $setID;
			$this->rawValues    = null;
			$this->values       = null;
			$this->getValues();
			return true;
		}

		return false;
	}

	public function getSetIDs($includeReadOnly = false) {
		$service = new _WV16_Service_Set();
		return $service->getSetIDs($this, $includeReadOnly);
	}

	public function getFirstSetID() {
		$service = new _WV16_Service_Set();
		return $service->getFirstSetID($this);
	}

	public function createSetCopy($sourceSetID = null) {
		$service = new _WV16_Service_Set();
		return $service->createSetCopy($this, $sourceSetID);
	}

	public function createReadOnlySet($sourceSetID = null) {
		$service = new _WV16_Service_Set();
		return $service->createReadOnlySet($this, $sourceSetID);
	}

	public function deleteSet($setID = null) {
		$service = new _WV16_Service_Set();
		return $service->deleteSet($this, $setID);
	}

	public function isReadOnly() {
		return _WV16_Service_Set::isReadOnlySet($this->currentSetID);
	}


	/* Hilfsmethoden für den User-Service */


	public function _setEverActivated() {
		$this->wasActivated = true;
	}
}
