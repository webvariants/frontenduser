<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 * 
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der 
 * beiliegenden LICENSE Datei und unter:
 * 
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz 
 */

class _WV16_User {
	const ERR_UNKNOWN_USER  = 1; 
	const ERR_INVALID_LOGIN = 2; 
	const ERR_PWD_TOO_SHORT = 3; 
	const ERR_LOGIN_EXISTS  = 4; 
	
	private $id;
	private $login;
	private $password;
	private $typeID;
	private $origTypeID;
	private $registered;
	private $attributes;
	private $groups;
	
	private static $instances = array();
	// hier merken wir uns für den aktuellen request, ob eine aktivierungsmail 
	// an irgendeinen user rausgeschickt wurde. (für eine nette bestätigungsmeldung
	// im addon-backend "aktivierungsmail wurde verschickt" und so..
	public static $sentActivationMail = false;
	
	public static function getCurrentUser() {
		$session = WV16_Session::getInstance('varisale', 60*60);
		
		try {
			$userID = $session->get('user_id');
			$user   = self::getInstance($userID);
		}
		catch ( Exception $e ) {
			$user = null;
		}
		
		return $user;
	}
	
	public static function getInstance($userID) {
		$userID = intval($userID);
		if (empty(self::$instances[$userID])) self::$instances[$userID] = new self($userID);
		return self::$instances[$userID];
	}
	
	private function __construct($id) {
		$sql = WV_SQL::getInstance();
		$data = $sql->fetch('*', 'wv16_users', 'id = '.$id);
		
		if (empty($data)) throw new Exception('Der Benutzer #'.$id.' konnte nicht gefunden werden!', self::ERR_UNKNOWN_USER);
		
		$this->id         = (int) $data['id'];
		$this->login      = $data['login'];
		$this->password   = $data['password'];
		$this->registered = $data['registered'];
		$this->typeID     = (int) $data['type_id'];
		$this->origTypeID = $this->typeID;
		$this->attributes = null;
		$this->groups     = array_map('intval', $sql->getArray('SELECT group_id FROM #_wv16_user_groups WHERE user_id = '.$this->id, '#_'));
	}
	
	public function getLogin() {
		return $this->login;
	}
	
	public function getID() {
		return $this->id;
	}
	
	public function getRegistered() {
		return $this->registered;
	}
	
	public function getTypeID() {
		return $this->typeID;
	}
	
	public function getType() {
		return _WV16_UserType::getInstance($this->typeID);
	}
	
	public function getGroupIDs() {
		return $this->groups;
	}
	
	public function getGroups() {
		$obj = array();
		foreach ($this->groups as $id) $obj[] = _WV16_Group::getInstance($id);
		return $obj;
	}
	
	public function getAttribute($attribute, $default = null) {
		return WV16_Users::userData($this, $attribute, $default);
	}
	
	public function setAttribute($attribute, $value) {
		$value     = strval($value);
		$attribute = _WV16::getIDForAttribute($attribute);
		$sql       = WV_SQL::getInstance();
		
		// Prüfen, ob das Attribut überhaupt zu dem aktuellen Benutzertyp gehört.
		// Dazu reicht es, die Liste der geholten Attribute durchzugehen, da ein
		// Benutzer immer alle Attribute hat, die zum Typ gehören (auch wenn sie
		// mit ihrem jeweiligen Standardwert belegt sind).
		
		if (!in_array($attribute, array_keys($this->getAttributes()))) { // getAttributes() holt die Attribute, falls nötig!
			return false;
		}
		
		// OK, das Attribut darf gesetzt werden. :-)
		
		$oldValue = $sql->fetch('value', 'wv16_user_values', 'user_id = '.$this->id.' AND attribute_id = '.$attribute);
		
		if ($oldValue === false || $attribute != $oldValue) {
			$this->attributes[$attribute] = new _WV16_UserValue($value, $attribute, $this);
			if ($oldValue === false) {
				$sql->query('INSERT INTO '.WV_SQL::getPrefix().'wv16_user_values '.
				'(user_id,attribute_id,value) VALUES('.$this->id.','.$attribute.','.
				'"'.mysql_real_escape_string($value).'")');
			}
			else {
				$sql->query('UPDATE '.WV_SQL::getPrefix().'wv16_user_values '.
				'SET value = "'.mysql_real_escape_string($value).'" WHERE '.
				'user_id = '.$this->id.' AND attribute_id = '.$attribute);
			}
		}
	}
	
	public function getAttributes() { // Na ja, eher UserValue-Objekte...
		if ($this->attributes === null) {
			$this->attributes = WV16_Users::getDataForUser($this->id, null);
			
			if ($this->attributes === null) {
				$this->attributes = array();
			}
			elseif (!is_array($this->attributes)) {
				$attr = $this->attributes;
				unset($this->attributes);
				$this->attributes[$attr->getAttributeName()] = $attr;
			}
			
			// von assoziativ auf normal-indiziert (IDs umschalten)
			$a = array();
			foreach ($this->attributes as $name => $attr) $a[$attr->getAttributeID()] = $attr;
			unset($this->attributes);
			$this->attributes = $a;
		}
		
		return $this->attributes;
	}
	
	public static function register($login, $password, $userType = null) {
		$sql = WV_SQL::getInstance();
		
		$password = trim($password);
		$login    = trim($login);
		$userType = _WV16::getIDForUserType($userType, true);
		
		if ($userType === null) $userType = _WV16::DEFAULT_USER_TYPE;
		
		if (!preg_match('#^[a-z0-9_.,;\#+-@]+$#i', $login)) {
			throw new Exception('Der Login enthält ungültige Zeichen.', self::ERR_INVALID_LOGIN);
		}
		
		if (strlen($password) < 6) {
			throw new Exception('Das Passwort ist zu kurz (mindestens 6 Zeichen!)', self::ERR_PWD_TOO_SHORT);
		}
		
		if ($sql->count('wv16_users','LOWER(login) = LOWER("'.mysql_real_escape_string($login).'")') != 0) {
			throw new Exception('Der Login ist bereits vergeben.', self::ERR_LOGIN_EXISTS);
		}
		
		$registered = date('Y-m-d H:i:s');
		
		$sql->query(sprintf('INSERT INTO %swv16_users (login,'.
			'password,registered,type_id) VALUES ("%s","","%s",%d)',
			WV_SQL::getPrefix(),
			mysql_real_escape_string($login),
			$registered,
			$userType
		));
		
		$userID = $sql->lastID();
		$pwhash = sha1($userID.$password.$registered);
		
		$sql->query(sprintf('UPDATE %swv16_users SET password = "%s" WHERE id = %d',
			WV_SQL::getPrefix(),
			$pwhash,
			$userID
		));
		
		$user = self::getInstance($userID);
		$user->addGroup(_WV16_Group::GROUP_UNCONFIRMED);
		
		// Attribute und ihre Standardwerte übernehmen
		
		$attributes = WV16_Users::getAttributesForUserType($userType);
		foreach ($attributes as $attr) {
			$sql->query('INSERT INTO '.WV_SQL::getPrefix().'wv16_user_values '.
				'(user_id,attribute_id,value) VALUES ('.$userID.','.$attr->getID().','.
				'"'.mysql_real_escape_string($attr->getDefaultValue()).'")');
		}
		
		return $user;
	}
	
	public function update() {
		$sql = WV_SQL::getInstance();
		
		if ($sql->count('wv16_users','LOWER(login) = LOWER("'.mysql_real_escape_string($this->login).'") AND id <> '.$this->id) != 0) {
			throw new Exception('Der Login ist bereits vergeben.', self::ERR_LOGIN_EXISTS);
		}
		
		$sql->query(sprintf('UPDATE %swv16_users SET login = "%s", '.
			'password = "%s", type_id = %d WHERE id = %d',
			WV_SQL::getPrefix(),
			mysql_real_escape_string($this->login),
			$this->password,
			$this->typeID,
			$this->id
		));
		
		if ($this->typeID != $this->origTypeID) {
			$oldTypesAttributes = WV16_Users::getAttributesForUserType($this->origTypeID);
			$newTypesAttributes = WV16_Users::getAttributesForUserType($this->typeID);
			
			foreach ($oldTypesAttributes as $idx => $attr) $oldTypesAttributes[$idx] = $attr->getID();
			foreach ($newTypesAttributes as $idx => $attr) $newTypesAttributes[$idx] = $attr->getID();
			
			$toDelete = array_diff($oldTypesAttributes, $newTypesAttributes);
			$toAdd    = array_diff($newTypesAttributes, $oldTypesAttributes);
			
			if (!empty($toDelete)) {
				$sql->query('DELETE FROM #_wv16_user_values WHERE '.
					'user_id = '.$this->id.' AND '.
					'attribute_id IN ('.implode(',', $toDelete).')', '#_');
			}
			
			foreach ($toAdd as $addID) {
				$attr = _WV16_Attribute::getInstance($addID);
				$sql->query('INSERT INTO '.WV_SQL::getPrefix().'wv16_user_values '.
					'(user_id,attribute_id,value) VALUES ('.$this->id.','.$addID.','.
					'"'.mysql_real_escape_string($attr->getDefaultValue()).'")');
			}
			
			$this->attributes = null; // neues Abrufen beim Aufruf von getAttributes() veranlassen
			$this->origTypeID = $this->typeID;
		}
	}
	
	public function delete() {
		$sql = WV_SQL::getInstance();
		$sql->query('DELETE FROM #_wv16_users WHERE id = '.$this->id, '#_');
		$sql->query('DELETE FROM #_wv16_user_groups WHERE user_id = '.$this->id, '#_');
		$sql->query('DELETE FROM #_wv16_user_values WHERE user_id = '.$this->id, '#_');
	}
	
	public function isInGroup($group) {
		$group = _WV16::getIDForGroup($group, false);
		return array_search($group, $this->groups) !== false;
	}
	
	public function addGroup($group) {
		$group = _WV16::getIDForGroup($group, false);
		$sql   = WV_SQL::getInstance();
		
//		if ($group == _WV16_Group::GROUP_UNCONFIRMED) {
//			// Das darf man nur per setConfirmed(false).
//			// Darf man wohl! Wir können bei removeAllGroups nur schwer sicherstellen, dass nichts Falsches gelöscht würde..
//			return false;
//		}
		
		if ($group == _WV16_Group::GROUP_ACTIVATED) {
			// wenn das die erste aktivierung des nutzers ist..
			if (!$this->wasEverActivated()) {
				// ..schicke ihm eine mail..
				WV16_Mailer::notifyUserOnActivation($this);
				self::$sentActivationMail = true;
				// ..und merke mir die aktivierung
				$this->storeActivation();
			}
		}
		
		if (!_WV16_Group::exists($group)) {
			return false;
		}
		
		if ($sql->count('wv16_user_groups', 'user_id = '.$this->id.' AND group_id = '.$group) == 0) {
			$sql->query('INSERT INTO #_wv16_user_groups (user_id,group_id) VALUES ('.$this->id.','.$group.')', '#_');
			$this->groups[] = $group;
		}
	}
	
	private function wasEverActivated() {
		$activations = WV16_Users::getConfig('activations');
		if (is_array($activations)) {
			return isset($activations[$this->getLogin()]);
		}
		return false;
	}
	
	private function storeActivation() {
		$activations = WV16_Users::getConfig('activations');
		$activations[$this->getLogin()] = array(time());
		WV16_Users::setConfig('activations', $activations);
	}
	
	public function removeGroup($group) {
		$group = _WV16::getIDForGroup($group, false);
//		if ($group == _WV16_Group::UNCONFIRMED_GROUP) return false;
		$index = array_search($group, $this->groups);
		WV_SQL::getInstance()->query('DELETE FROM #_wv16_user_groups WHERE user_id = '.$this->id.' AND group_id = '.$group, '#_');
		if ($index !== false) unset($this->groups[$index]);
	}
	
	public function removeAllGroups() {
		WV_SQL::getInstance()->query('DELETE FROM #_wv16_user_groups WHERE user_id = '.$this->id, '#_');
		$this->groups = array();
	}
	
	public function setConfirmed($isConfirmed = true) {
		if ($isConfirmed) {
			$this->removeGroup(_WV16_Group::GROUP_UNCONFIRMED);
			$this->addGroup(_WV16_Group::GROUP_CONFIRMED);
		}
		else {
			$this->removeAllGroups();
			$this->addGroup(_WV16_Group::GROUP_UNCONFIRMED);
		}
	}
	
	public function isConfirmed() {
		return !$this->isInGroup(_WV16_Group::GROUP_UNCONFIRMED);
	}
	
	public function setLogin($login) {
		$login = trim($login);
		if (!preg_match('#^[a-z0-9_.,;\#+-@]+$#i', $login)) {
			throw new Exception('Der Login enthält ungültige Zeichen.', self::ERR_INVALID_LOGIN);
		}
		$this->login = $login;
	}
	
	public function setPassword($password) {
		$password = trim($password);
		if (strlen($password) < 6) {
			throw new Exception('Das Passwort ist zu kurz (mindestens 6 Zeichen!)', self::ERR_PWD_TOO_SHORT);
		}
		$this->password = sha1($this->id.$password.$this->registered);
	}
	
	public function setUserType($userType) {
		$userType = _WV16::getIDForUserType($userType, false);
		if (_WV16_UserType::exists($userType)) $this->typeID = $userType;
	}
	
	public function canAccess($object, $objectType = null) {
		foreach ($this->groups as $group) {
			$group = _WV16_Group::getInstance($group);
			if ($group->canAccess($object, $objectType)) return true;
		}
		return false;
	}
}
