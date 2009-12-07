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

abstract class WV16_Users extends _WV16_DataHandler
{
	const ANONYMOUS              = 0;
	const ERR_USER_UNKNOWN       = 1;
	const ERR_INVALID_LOGIN      = 1;
	const ERR_USER_NOT_ACTIVATED = 2;
	
	public static function getConfig($name, $default = null)
	{
		$value = WV_Registry::get('wv16_'.$name);
		return $value === null ? $default : $value;
	}
	
	public static function setConfig($name, $value)
	{
		return WV_Registry::set('wv16_'.$name, $value);
	}
	
	// $max = -1 für unendlich
	public static function getAllUsers($orderBy = 'id', $direction = 'asc', $offset = 0, $max = 10)
	{
		$direction = $direction == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$users     = array();
		$sql       = WV_SQLEx::getInstance();
		
		// "18446744073709551615" für "alles" wird vom MySQL-Handbuch empfohlen:
		// http://dev.mysql.com/doc/refman/5.0/en/select.html
		
		$max   = $max === -1 ? 18446744073709551615 : abs((int) $max);
		$query = 'SELECT id FROM #_wv16_users WHERE 1 ORDER BY '.$orderBy.' '.$direction.' LIMIT '.$offset.','.$max;
		
		$sql->queryEx($query, array(), '#_');
		
		foreach ($sql as $row) {
			$users[$row['id']] = _WV16_User::getInstance($row['id']);
		}
		
		return $users;
	}
	
	public static function getAllUsersInGroup($group, $orderBy = 'id', $direction = 'asc', $offset = 0, $max = 10)
	{
		$direction = $direction == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$sql       = WV_SQLEx::getInstance();
		$users     = array();
		$group     = _WV16::getIDForGroup($group, false);
		
		// "18446744073709551615" für "alles" wird vom MySQL-Handbuch empfohlen:
		// http://dev.mysql.com/doc/refman/5.0/en/select.html
		$max   = $max === -1 ? 18446744073709551615 : abs((int) $max);
		$query = 'SELECT id '.
			'FROM #_wv16_users u '.
			'LEFT JOIN #_wv16_user_groups ug ON u.id = ug.user_id '.
			'WHERE group_id = ? ORDER BY '.$orderBy.' '.$direction.' LIMIT '.$offset.','.$max;
		
		$sql->queryEx($query, $group, '#_');
		
		foreach ($sql as $row) {
			$users[$row['id']] = _WV16_User::getInstance($row['id']);
		}
		
		return $users;
	}
	
	public static function getAllGroups($orderBy = 'title', $direction = 'asc')
	{
		$direction = $direction == 'desc' ? 'DESC' : 'ASC';
		$sql       = WV_SQLEx::getInstance();
		$query     = 'SELECT id FROM #_wv16_groups WHERE 1 ORDER BY '.$orderBy.' '.$direction;
		$groups    = array();
		
		$sql->queryEx($query, array(), '#_');
		
		foreach ($sql as $row) {
			$groups[$row['id']] = _WV16_Group::getInstance($row['id']);
		}
		
		return $groups;
	}
	
	public static function isLoggedIn()
	{
		return self::getCurrentUser() ? true : false;
	}
	
	public static function getCurrentUser()
	{
		try {
			$userID = rex_session('frontenduser', 'int', self::ANONYMOUS);
			return _WV16_User::getInstance($userID);
		}
		catch (Exception $e) {
			return null;
		}
	}
	
	public static function logout()
	{
		rex_set_session('frontenduser', self::ANONYMOUS);
		return null;
	}
	
	public static function login($login, $password) {
		$userObj = self::getUser($login);
		
		if ($userObj->isInGroup(_WV16_Group::GROUP_ACTIVATED) && self::checkPassword($userObj, $password)) {
			self::loginUser($userObj);
			return $userObj;
		}
		else {
			throw new Exception('user not activated', self::ERR_USER_NOT_ACTIVATED);
		}
	}
	
	public static function getUser($login){
		$sql 	= _WV_SQL::getInstance();
		$login	= trim($login);
		
		$userdata = $sql->fetch('*', 'wv16_users', 'deleted = 0 AND LOWER(login) = LOWER("'.mysql_real_escape_string($login).'")');
				
		if (empty($userdata)) {
			throw new Exception('User unknown', self::ERR_USER_UNKNOWN);
		}
		
		return _WV16_User::getInstance((int) $userdata['id']);
	}
	
	public static function checkPassword(_WV16_User $user, $password) {
		$password = trim($password);

		return sha1($user->getId().$password.$user->getRegistered()) === $user->getPasswordHash();
	}
	
	public static function loginUser(WV16_User $user)
	{
		rex_set_session('frontenduser', $user->getID());
	}
	
	public static function register($login, $password, $userType = null)
	{
		return _WV16_User::register($login, $password, $userType);
	}
	
	public static function isProtected($object, $objectType = null, $inherit = true)
	{
		list($objectID, $objectType) = _WV16::identifyObject($object, $objectType);
		$sql = WV_SQLEx::getInstance();
		
		// Prüfen, ob es sich, wenn wir einen Artikel haben, es sich
		// gleichzeitig auch um eine Kategorie handelt.
		
		$isStartpage = $sql->saveFetch('startpage', 'article', 'id = ?', $objectID) && $objectType == _WV16::TYPE_ARTICLE;
		
		// Wollen wir wirklich nur die Rechte für dieses eine Objekt,
		// egal, ob es vererbte Rechte gibt?
		
		if (!$inherit) {
			$privileges = $sql->count('wv16_rights', 'object_id = ? AND object_type = ?', array($objectID, $objectType));
			return $privileges > 0;
		}
		
		// Die Berechtigung für dieses Objekt allein abrufen (explizite Rechte?)
		
		$privileges = $sql->saveFetch('*', 'wv16_rights', 'object_id = ? AND object_type = ?', array($objectID, $objectType));
		if (!empty($privileges)) return true;
		
		// Wenn es sich um eine Datei handelt, gibt es keine Vererbung. Und wenn
		// es dann keine Rechte für die Datei gibt, ist das Objekt auch nicht
		// geschützt.
		
		if ($objectType == _WV16::TYPE_MEDIUM) return false;
		
		// Wir brauchen jetzt das Elternelement. Bei einem Artikel ist das die
		// ihn beinhaltende Kategorie, bei einer Kategorie die Elternkategorie.
		// Wenn es sich um eine Startseite einer Kategorie handelt, ist die
		// Elternkategorie logischerweise direkt "der Artikel selbst".

		$parentCategory = $isStartpage ? $objectID : $sql->saveFetch('re_id', 'article', 'id = ?', $objectID);
		if ($parentCategory == 0) return false; // Artikel/Kategorie der obersten Ebene, da generell alle Objekte erlaubt sind, hören wir hier auf.
		return self::isProtected(intval($parentCategory), _WV16::TYPE_CATEGORY);
	}
	
	public static function protect($object, $objectType = null, $loginArticle = null, $accessDeniedArticle = null) {
		if (self::isProtected($object, $objectType)) {
			$user = WV16_Users::getCurrentUser();
			$url  = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
			
			/*if (!$user) {
				rex_set_session('frontenduser_target_url', $url);
				if($loginArticle == null) $loginArticle = self::getConfig('login_article', '');
				$loginArticle = OOArticle::getArticleById(WV2::getIDForArticle($loginArticle));
				header('Location: '.$loginArticle->getUrl());
				exit;
			}*/
			
			$access = $user && $user->canAccess($object, $objectType);
			
			if (!$access) {
				rex_set_session('frontenduser_target_url', $url);
				if($accessDeniedArticle == null) $accessDeniedArticle = self::getConfig('access_denied_article', '');
				$accessDeniedArticle = OOArticle::getArticleById(self::getConfig('access_denied_article', ''));
				header('Location: '.$accessDeniedArticle->getUrl());
				exit;
			}
		}
	}
	
	public static function generateConfirmationCode($login)
	{
		return strtoupper(substr(md5($login.' '.rand()), 0, 8));
	}
	
	public static function checkConfirmationCode($login, $code)
	{
		return $code == self::getConfirmationCode($login);
	}
	
	public static function getConfirmationCode($login)
	{
		try {
			$user = _WV16_User::getInstanceByLogin($login);
			return $user->getConfirmationCode();
		}
		catch (Exception $e) {
			return null;
		}
	}
	
	public static function removeConfirmationCode($login)
	{
		try {
			$user = _WV16_User::getInstanceByLogin($login);
			$user->setConfirmationCode('');
			$user->update();
			return true;
		}
		catch (Exception $e) {
			return false;
		}
	}
	
	public static function verifyEmail($code) {
		$code_db = WV4_Registry::get('wv16_code_db');
		if ($code_db === null) return;
		if (isset($code_db[$code])){
			$user = self::getUser($code_db[$code]);
			$user->addGroup(_WV16_Group::GROUP_CONFIRMED);
			$user->removeGroup(_WV16_Group::GROUP_UNCONFIRMED);
			self::removeVerificationCode($code);
		} 
	}
		
	public static function recoverPassword(_WV16_User $user, $email, $newpassword){
		$email = trim($email);
		$newpassword = trim($newpassword);
		$orig_email = $user->getAttribute('email')->getValue();
		
		if($orig_email == $email){
			$user->setPassword($newpassword);
			$user->update();
			WV16_Mailer::sendPasswordRecovery($user, $orig_email, $newpassword);		
		}else throw new Exception('Wrong email address', 0);
	}
	
	public static function isReadOnlySet($setID) {
		return _WV16_User::isReadOnlySet($setID);
	}
}
