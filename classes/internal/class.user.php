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

class _WV16_User
{
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
	protected $wasActivated;
	protected $currentSetID;
	
	private static $instances = array();
	
	public static function getInstance($userID)
	{
		$userID = (int) $userID;
		
		if (isset(self::$instances[$userID])) {
			return self::$instances[$userID];
		}
		
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.users';
		$instance  = $cache->get($namespace, $userID);
		
		if (!$instance) {
			if ($cache->lock($namespace, $userID)) {
				try {
					$instance = new self($userID);
					$cache->set($namespace, $userID, $instance);
					$cache->unlock($namespace, $userID);
				}
				catch (Exception $e) {
					$cache->unlock($namespace, $userID);
					throw $e;
				}
			}
			else {
				$instance = $cache->waitForObject($namespace, $userID);
				
				if (!$instance) {
					$instance = new self($userID);
				}
			}
		}
		
		self::$instances[$userID] = $instance;
		return self::$instances[$userID];
	}
	
	private function __construct($id)
	{
		$sql  = WV_SQLEx::getInstance();
		$data = $sql->saveFetch('*', 'wv16_users', 'id = ?', $id);
		
		if (empty($data)) {
			throw new WV16_Exception('Der Benutzer #'.$id.' konnte nicht gefunden werden!', self::ERR_UNKNOWN_USER);
		}
		
		$this->id           = (int) $data['id'];
		$this->login        = $data['login'];
		$this->password     = $data['password'];
		$this->registered   = $data['registered'];
		$this->typeID       = (int) $data['type_id'];
		$this->origTypeID   = $this->typeID;
		$this->values       = null;
		$this->deleted      = (boolean) $data['deleted'];
		$this->wasActivated = (boolean) $data['was_activated'];
		$this->currentSetID = WV16_Users::getFirstSetID($this->id);
		$this->groups       = $sql->getArray('SELECT group_id FROM #_wv16_user_groups WHERE user_id = ?', $this->id, '#_');
	}
	
	public static function register($login, $password, $userType = null, $useTransaction = true)
	{
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			$password = trim($password);
			$login    = trim($login);
			$userType = _WV16::getIDForUserType($userType, true);
			
			if ($userType === null) {
				$userType = _WV16::DEFAULT_USER_TYPE;
			}
			
			if (empty($login)) {
				throw new WV_InputException('Der Login darf nicht leer sein.', self::ERR_INVALID_LOGIN);
			}
			
			if (!preg_match('#^[a-z0-9_.,;\#+-@]+$#i', $login)) {
				throw new WV_InputException('Der Login enthält ungültige Zeichen.', self::ERR_INVALID_LOGIN);
			}
			
			if ($sql->count('wv16_users','LOWER(login) = ?', strtolower($login)) != 0) {
				throw new WV_InputException('Der Login ist bereits vergeben.', self::ERR_LOGIN_EXISTS);
			}
			
			self::testPassword($password);
			
			$registered = date('Y-m-d H:i:s');
			
			$sql->queryEx(
				'INSERT INTO #_wv16_users (login,password,registered,type_id) VALUES (?,"",?,?)',
				array($login, $registered, $userType), '#_'
			);
			
			$userID = $sql->lastID();
			$pwhash = sha1($userID.$password.$registered);
			
			$sql->queryEx('UPDATE #_wv16_users SET password = ? WHERE id = ?', array($pwhash, $userID), '#_');
			
			$user = self::getInstance($userID);
			$user->addGroup(_WV16_Group::GROUP_UNCONFIRMED, false);
			
			// Attribute und ihre Standardwerte übernehmen
			// TODO: Das kriegen wir auch mit einer Query hin.
			
			$attributes = WV16_Users::getAttributesForUserType($userType);
			
			foreach ($attributes as $attr) {
				$sql->queryEx(
					'INSERT INTO #_wv16_user_values (user_id,attribute_id,set_id,value) VALUES (?,?,?,?)',
					array($userID, $attr->getID(), 1, $attr->getDefault()), '#_'
				);
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			return $user;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return null;
		}
	}
	
	public function update($useTransaction = true)
	{
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			if ($sql->count('wv16_users','LOWER(login) = ? AND id <> ?', array(strtolower($this->login), $this->id)) != 0) {
				throw new WV_InputException('Der Login ist bereits vergeben.', self::ERR_LOGIN_EXISTS);
			}
			
			$sql->queryEx(
				'UPDATE #_wv16_users SET login = ?, password = ?, type_id = ? WHERE id = ?',
				array($this->login, $this->password, $this->typeID, $this->id), '#_'
			);
			
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
						'DELETE FROM #_wv16_user_values WHERE user_id = ? AND attribute_id IN ('.$markers.')',
						$this->id, '#_'
					);
				}
				
				if (!empty($toAdd)) {
					$markers = implode(',', $toAdd);
					
					$sql->queryEx(
						'INSERT INTO #_wv16_user_values (user_id,attribute_id,value) '.
						'SELECT ?,id,default_value FROM #_wv16_attributes WHERE id IN ('.$markers.')',
						$this->id, '#_'
					);
				}
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			if ($this->typeID != $this->origTypeID) {
				$this->values     = null; // neues Abrufen beim Aufruf von getAttributes() veranlassen
				$this->origTypeID = $this->typeID;
			}
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.users', true);
			$cache->flush('frontenduser.uservalues', true);
			$cache->flush('frontenduser.lists', true);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public function delete($useTransaction = true)
	{
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			$sql->queryEx('DELETE FROM #_wv16_users WHERE id = ?', $this->id, '#_');
			$sql->queryEx('DELETE FROM #_wv16_user_groups WHERE user_id = ?', $this->id, '#_');
			$sql->queryEx('DELETE FROM #_wv16_user_values WHERE user_id = ?', $this->id, '#_');
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.users', true);
			$cache->flush('frontenduser.uservalues', true);
			$cache->flush('frontenduser.lists', true);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public static function exists($login)
	{
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.users';
		$cacheKey  = WV_Cache::generateKey('mapping', $login);
		
		if ($cache->exists($namespace, $cacheKey)) {
			return true;
		}
		
		$sql = WV_SQLEx::getInstance();
		$id  = $sql->saveFetch('id', 'wv16_users','LOWER(login) = ?', strtolower($login));
		
		if ($id !== false) {
			$cache->set($namespace, $cacheKey, (int) $id);
			return true;
		}
		
		return false;
	}
	
	public function getLogin()      { return $this->login;      }
	public function getID()         { return $this->id;         }
	public function getRegistered() { return $this->registered; }
	public function getTypeID()     { return $this->typeID;     }
	public function getGroupIDs()   { return $this->groups;     }
	
	public function getType()
	{
		return _WV16_UserType::getInstance($this->typeID);
	}
	
	public function getGroups()
	{
		$obj = array();
		
		foreach ($this->groups as $id) {
			$obj[] = _WV16_Group::getInstance($id);
		}
		
		return $obj;
	}
	
	public function getValue($attribute, $default = null)
	{
		return WV16_Users::userData($this, $attribute, $default);
	}
	
	public function setValue($attribute, $value, $useTransaction = true)
	{
		return WV16_Users::setDataForUser($this, $attribute, $value, $useTransaction);
	}
	
	public function getValues()
	{
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
			
			$cache     = WV_DeveloperUtils::getCache();
			$namespace = 'frontenduser.users';
			
			$cache->set($namespace, $this->id, $this);
		}
		
		return $this->values;
	}
	
	public function isInGroup($group)
	{
		$group = _WV16::getIDForGroup($group, false);
		return array_search($group, $this->groups) !== false;
	}
	
	public function addGroup($group, $useTransaction = true)
	{
		$group = _WV16::getIDForGroup($group, false);
		$sql   = WV_SQLEx::getInstance();
		$mode  = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			if (_WV16_Group::exists($group)) {
				if ($sql->count('wv16_user_groups', 'user_id = ? AND group_id = ?', array($this->id, $group)) == 0) {
					$sql->queryEx('INSERT INTO #_wv16_user_groups (user_id,group_id) VALUES (?,?)', array($this->id, $group), '#_');
					$this->groups[] = $group;
				}
			}
			
			// Wenn der Benutzer zum ersten Mal aktiviert wurde, merken wir uns
			// das in der Datenbank. Dies ist eine kleine Hilfe für umliegende
			// Funktionen, die auf die erste Aktivierung reagieren möchten.
			
			if (self::isInGroup(_WV16_Group::GROUP_ACTIVATED)) {
				$this->wasActivated = true;
				$this->update(false);
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.users', true);
			$cache->flush('frontenduser.lists', true);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public function wasEverActivated()
	{
		return $this->wasActivated;
	}
	
	public function wasNeverActivated()
	{
		return !$this->wasActivated;
	}
	
	public function removeGroup($group, $useTransaction = true)
	{
		$group = _WV16::getIDForGroup($group, false);
		$index = $this->groups === null ? false : array_search($group, $this->groups);
		$sql   = WV_SQLEx::getInstance();
		$mode  = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			$query = 'DELETE FROM #_wv16_user_groups WHERE user_id = ? AND group_id = ?';
			
			$sql->queryEx($query, array($this->id, $group), '#_');
			
			if ($index !== false) {
				unset($this->groups[$index]);
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.users', true);
			$cache->flush('frontenduser.lists', true);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public function removeAllGroups($useTransaction = true)
	{
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			$sql->queryEx('DELETE FROM #_wv16_user_groups WHERE user_id = ?', $this->id, '#_');
			$this->groups = array();
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.users', true);
			$cache->flush('frontenduser.lists', true);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public function setConfirmed($isConfirmed = true, $useTransaction = true)
	{
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			if ($isConfirmed) {
				$this->removeGroup(_WV16_Group::GROUP_UNCONFIRMED, false);
				$this->addGroup(_WV16_Group::GROUP_CONFIRMED, false);
			}
			else {
				$this->removeAllGroups(false);
				$this->addGroup(_WV16_Group::GROUP_UNCONFIRMED, false);
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.users', true);
			$cache->flush('frontenduser.lists', true);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public function isConfirmed()
	{
		return !$this->isInGroup(_WV16_Group::GROUP_UNCONFIRMED);
	}
	
	public function setLogin($login)
	{
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
	
	public function setPassword($password)
	{
		// Benutzer, die mit Daten befüllt sind, die aus read-only Sets kommen, können sich nicht ändern.
		
		if ($this->isReadOnly()) {
			return false;
		}
		
		$password = trim($password);
		self::testPassword($password);
		$this->password = sha1($this->id.$password.$this->registered);
	}
	
	public static function testPassword($password)
	{
		if (strlen($password) < 6) {
			throw new WV_InputException('Das Passwort ist zu kurz (mindestens 6 Zeichen!)', self::ERR_PWD_TOO_SHORT);
		}
		
		// Besteht das Passwort nur aus Zahlen?
		
		if (preg_match('#^[0-9]$#', $password)) {
			throw new WV_InputException('Das Passwort ist anfällig gegenüber Wörterbuch-Angriffen!');
		}
		
		// TODO: Hier genauere und im Backend konfigurierbare Testroutine einbauen.
		// Entsprechende Implementierungen finden sich in der class.securepwd.php.
		
		return true;
	}
	
	public function setUserType($userType)
	{
		// Benutzer, die mit Daten befüllt sind, die aus read-only Sets kommen, können sich nicht ändern.
		
		if ($this->isReadOnly()) {
			return false;
		}
		
		$userType = _WV16::getIDForUserType($userType, false);
		
		if (_WV16_UserType::exists($userType)) {
			$this->typeID = $userType;
		}
	}
	
	public function canAccess($object, $objectType = null)
	{
		foreach ($this->groups as $group) {
			$group = _WV16_Group::getInstance($group);
			
			if ($group->canAccess($object, $objectType)) {
				return true;
			}
		}
		
		return false;
	}
	
	public function setSetID($setID)
	{
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
	
	public function getSetID()
	{
		return $this->currentSetID;
	}
	
	public function isDeleted()
	{
		return $this->deleted;
	}
	
	public function getSetIDs($includeReadOnly = false)
	{
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = WV_Cache::generateKey('set_ids', $this->id, $includeReadOnly);
		
		$ids = $cache->get($namespace, $cachekey, null);
		
		if (is_array($ids)) {
			return $ids;
		}
		
		$includeReadOnly = $includeReadOnly ? '' : ' AND set_id >= 0';
		
		$ids = WV_SQLEx::getInstance()->getArray(
			'SELECT DISTINCT set_id FROM #_wv16_user_values WHERE user_id = ?'.$includeReadOnly.' ORDER BY set_id',
			$this->id, '#_', WV_SQLEx::RETURN_FALSE
		);
		
		$ids = array_map('intval', $ids);
		$cache->set($namespace, $cacheKey, $ids);
		return $ids;
	}
	
	public function createSetCopy($sourceSetID = null)
	{
		$setID = $sourceSetID === null ? WV16_Users::getFirstSetID($this->id) : (int) $sourceSetID;
		$newID = WV_SQLEx::getInstance()->saveFetch('MAX(set_id)', 'wv16_user_values', 'user_id = ?', $this->id) + 1;
		
		$this->copySet($setID, $newID);
		
		$cache = WV_DeveloperUtils::getCache();
		$cache->flush('frontenduser.lists', true);
		return $newID;
	}
	
	public function createReadOnlySet($sourceSetID = null)
	{
		$setID = $sourceSetID === null ? WV16_Users::getFirstSetID($this->id) : (int) $sourceSetID;
		$newID = WV_SQLEx::getInstance()->saveFetch('MIN(set_id)', 'wv16_user_values', 'user_id = ?', $this->id) - 1;
		
		// Ab Version 1.2.1 sind die Standard-IDs >= 0. Um Konflikten aus dem Weg zu gehen, wenn alte
		// Daten aktualisiert werden, stellen wir hier sicher, dass die erste ReadOnly-ID garantiert
		// kleiner als 0 ist.
		
		if ($newID == 0) {
			$newID = -1;
		}
		
		$this->copySet($setID, $newID);
		
		$cache = WV_DeveloperUtils::getCache();
		$cache->flush('frontenduser.lists', true);
		return $newID;
	}
	
	protected function copySet($sourceSet, $targetSet)
	{
		return WV_SQLEx::getInstance()->queryEx(
			'INSERT INTO #_wv16_user_values '.
			'SELECT user_id,attribute_id,?,value FROM #_wv16_user_values WHERE user_id = ? AND set_id = ?',
			array($targetSet, $this->id, $sourceSet), '#_', WV_SQLEx::RETURN_FALSE
		);
	}
	
	public function isReadOnly()
	{
		return self::isReadOnlySet($this->currentSetID);
	}
	
	public static function isReadOnlySet($setID)
	{
		return $setID < 0;
	}
}
