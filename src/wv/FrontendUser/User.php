<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace wv\FrontendUser;

class User extends \WV_Object implements UserInterface {
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
	private $setDataCache = array();

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
	 * @return User  der entsprechende Benutzer
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

	protected function __construct($id) {
		$sql  = \WV_SQL::getInstance();
		$data = $sql->fetch('*', 'wv16_users', 'id = ?', $id);

		if (empty($data)) {
			throw new Exception('Der Benutzer #'.$id.' konnte nicht gefunden werden!');
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
	 * @return User  der neu erzeugte Benutzer
	 */
	public static function register($login, $password, $userType = null) {
		$service = new Service\UserService();
		$userID  = $service->register($login, $password, $userType);

		return self::getInstance($userID);
	}

	public function update() {
		$service = new Service\UserService();
		$service->update($this);

		$this->values = null;
	}

	public function delete() {
		$service = new Service\UserService();
		$service->delete($this);
	}

	/**
	 * @return boolean  true, falls ja, sonst false
	 */
	public static function exists($login) {
		$service = new Service\UserService();
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
	public function getGroupNames()       { return $this->groups;           }
	public function isActivated()         { return $this->activated;        }
	public function isConfirmed()         { return $this->confirmed;        }
	public function getConfirmationCode() { return $this->confirmationCode; }
	public function getSetID()            { return $this->currentSetID;     }
	public function isDeleted()           { return $this->deleted;          }
	public function wasEverActivated()    { return $this->wasActivated;     }
	public function wasNeverActivated()   { return !$this->wasActivated;    }

	/**
	 * @return UserType  der Benutzertyp als Objekt
	 */
	public function getType() {
		return Factory::getUserType($this->type);
	}

	/**
	 * @return array  Liste aller Gruppen als Objekte
	 */
	public function getGroups() {
		$return = array();

		foreach ($this->groups as $name) {
			$return[$name] = Group::getInstance($name);
		}

		return $return;
	}

	/**
	 * @return boolean  check whether attribute exists or not
	 */
	public function hasAttribute($attribute) {
		$values = $this->getValues();
		return array_key_exists($attribute, $values);
	}

	/**
	 * @return mixed  der Benutzerwert
	 */
	public function getValue($attribute, $default = null) {
		$values = $this->getValues();
		return array_key_exists($attribute, $values) ? $values[$attribute] : $default;
	}

	/**
	 * @return mixed  der Benutzerwert
	 */
	public function toString($format) {
		return Users::replaceAttributes($format, $this);
	}

	/**
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function setSerializedValue($attribute, $value) {
		if (!is_string($value) && !is_int($value) && $value !== null) {
			throw new Exception('A serialized value must be either NULL or a string, got '.gettype($value).'.');
		}

		if (!$this->hasAttribute($attribute)) {
			throw new Exception('User does not have a "'.$attribute.'" attribute.');
		}

		$service = new Service\ValueService();
		$written = $service->write($this, null, $attribute, $value);

		if ($written) {
			$cache = \sly_Core::cache();
			$cache->delete('frontenduser.users', $this->id);
			$cache->delete('frontenduser.users.firstsets', $this->id);
			$cache->flush('frontenduser.lists', true);

			$this->rawValues = null;
			$this->values    = null;
		}

		return $written;
	}

	/**
	 * Set a user's attribute value
	 *
	 * This should be used to set the native value (like an array for the select
	 * datatype or a boolean for the boolen datatype). The value will
	 * automatically be serialized and then stored.
	 *
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function setRawValue($attribute, $value) {
		$value = Factory::getAttribute($attribute)->serialize($value);
		return $this->setSerializedValue($attribute, $value);
	}

	/**
	 * Set a user's attribute value
	 *
	 * @deprecated      use the more descriptive setSerializedValue() method instead
	 * @return boolean  true im Erfolgsfall, sonst false
	 */
	public function setValue($attribute, $value) {
		return $this->setSerializedValue($attribute, $value);
	}

	public function getValues($raw = false) {
		// Wurden die Werte noch nicht abgerufen?

		if ($this->rawValues === null) {
			$service         = new Service\ValueService();
			$this->rawValues = $service->read($this, null, true);

			// Benutzer neu cachen

			$cache     = \sly_Core::cache();
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
				$this->values[$attr] = Factory::getAttribute($attr)->deserialize($rawValue);
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
		$v = $this->getValue($attribute);

		if ($v === null) {
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
		if ($this->isReadOnly()) return false;

		$service = new Service\UserService();

		if ($service->addToGroup($this, $group)) {
			$this->groups[] = $group;
			return true;
		}

		return false;
	}

	public function removeGroup($group) {
		if ($this->isReadOnly()) return false;

		$service = new Service\UserService();
		$service->removeFromGroup($this, $group);

		$index = array_search($group, $this->groups);

		if ($index !== false) {
			unset($this->groups[$index]);
		}

		return true;
	}

	public function removeAllGroups() {
		if ($this->isReadOnly()) return false;
		$service = new Service\UserService();
		return $service->removeFromAllGroups($this);
	}

	public function setConfirmationCode($code = null) {
		if ($this->isReadOnly()) return false;

		$code = $code === null ? Users::generateConfirmationCode($this->login) : substr($code, 0, 20);
		$this->confirmationCode = $code;

		return $code;
	}

	/* Setter */

	public function setConfirmed($isConfirmed = true, $confirmationCode = null) {
		if ($this->isReadOnly()) return false;

		// Auf Wunsch kann auch diese Methode die Überprüfung auf den
		// Bestätigungscode selbst durchführen.

		if (is_string($confirmationCode)) {
			$isConfirmed = $this->confirmationCode == $confirmationCode;
		}

		$this->confirmed        = $isConfirmed;
		$this->confirmationCode = null;
	}

	public function setActivated($isActivated = true) {
		if ($this->isReadOnly()) return false;
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
			throw new Exception('Das Passwort ist zu kurz (mindestens 6 Zeichen!)', Users::ERR_PWD_TOO_SHORT);
		}

		// Besteht das Passwort nur aus Zahlen?

		if (preg_match('#^[0-9]$#', $password)) {
			throw new Exception('Das Passwort ist anfällig gegenüber Wörterbuch-Angriffen!', Users::ERR_PWD_TOO_WEAK);
		}

		return true;
	}

	public function setUserType($userType) {
		if ($this->isReadOnly()) return false;

		try {
			Factory::getUserType($userType);
			$this->type = $userType;

			return true;
		}
		catch (\Exception $e) {
			return false;
		}
	}

	/* Set-Management */

	public function setSetID($setID) {
		$this->setDataCache[$this->currentSetID] = array(
			'rawValues' => $this->rawValues,
			'values'    => $this->values
		);

		if (array_key_exists($setID, $this->setDataCache)) {
			$this->rawValues = $this->setDataCache[$setID]['rawValues'];
			$this->values = $this->setDataCache[$setID]['values'];
			$this->currentSetID = $setID;
			return false;
		}

		if ($this->currentSetID === $setID) {
			return false;
		}

		$setID = (int) $setID;
		$sql   = \WV_SQL::getInstance();

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
		$service = new Service\SetService();
		return $service->getSetIDs($this, $includeReadOnly);
	}

	public function getFirstSetID() {
		$service = new Service\SetService();
		return $service->getFirstSetID($this);
	}

	public function createSetCopy($sourceSetID = null, $empty = false) {
		$service = new Service\SetService();
		return $service->createSetCopy($this, $sourceSetID, $empty);
	}

	public function createReadOnlySet($sourceSetID = null) {
		$service = new Service\SetService();
		return $service->createReadOnlySet($this, $sourceSetID);
	}

	public function deleteSet($setID = null) {
		$service = new Service\SetService();
		return $service->deleteSet($this, $setID);
	}

	public function isReadOnly() {
		return Service\SetService::isReadOnlySet($this->currentSetID);
	}

	/* Hilfsmethoden für den User-Service */

	public function _setEverActivated() {
		$this->wasActivated = true;
	}

	public function _setDeleted() {
		$this->deleted = true;
	}
}
