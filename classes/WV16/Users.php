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

abstract class WV16_Users extends _WV16_DataHandler {
	const ANONYMOUS              = 0;
	const ERR_USER_UNKNOWN       = 1;
	const ERR_INVALID_LOGIN      = 2;
	const ERR_USER_NOT_ACTIVATED = 3;

	public static function clearCache() {
		$cache = WV_DeveloperUtils::getCache();
		$cache->flush('frontenduser', true);
	}

	public static function loginExists($login) {
		return _WV16_User::exists($login);
	}

	public static function getConfig($name, $default = null) {
		global $REX;

		if (rex_addon::isAvailable('global_settings')) {
			$value = WV8_Settings::getValue('frontenduser', $name);
			return empty($value) ? $default : $value; //Wenn leere Felder abgespeichert werden, sind sie ja nicht NULL
		}

		// Keine Global Settings :-( Also ab die Registry

		return WV_Registry::get('frontenduser_'.$name, $default);
	}

	public static function setConfig($name, $value) {
		global $REX;

		if (rex_addon::isAvailable('global_settings')) {
			try {
				$setting = _WV8_Setting::getInstance('frontenduser', $name);
				$setting->setValue($value, WV_Redaxo::clang());
				$setting->update();

				return true;
			}
			catch (Exception $e) {
				return false;
			}
		}

		// Keine Global Settings :-( Also ab die Registry

		WV_Registry::set('frontenduser_'.$name, $value);
		return true;
	}

	public static function getTotalUsers($where = '1') {
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = WV_Cache::generateKey('total_users', $where);
		$total     = $cache->get($namespace, $cacheKey, -1);

		if ($total < 0) {
			$sql   = WV_SQLEx::getInstance();
			$total = $sql->count('wv16_users', $where, array(), '#_');
			$total = $total === false ? -1 : (int) $total;
			$cache->set($namespace, $cacheKey, $total);
		}

		return $total;
	}

	// $max < 0 für unendlich
	public static function getAllUsers($where, $orderBy = 'id', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = WV_Cache::generateKey('users_by', $where, $orderBy, $direction, $offset, $max);

		$users = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($users)) {
			$sql    = WV_SQLEx::getInstance();
			$query  = 'SELECT id FROM #_wv16_users WHERE '.$where.' ORDER BY '.$orderBy.' '.$direction;
			$max    = $max < 0 ? '18446744073709551615' : (int) $max;
			$query .= ' LIMIT '.$offset.','.$max;

			$users = $sql->getArray($query, array(), '#_');
			$cache->set($namespace, $cacheKey, $users);
		}

		$result = array();

		foreach ($users as $userID) {
			$result[$userID] = _WV16_User::getInstance($userID);
		}

		return $result;
	}

	public static function getAllUsersInGroup($group, $orderBy = 'id', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.lists';
		$groupID   = _WV16_FrontendUser::getIDForGroup($group, false);
		$cacheKey  = WV_Cache::generateKey('users_by', $group, $orderBy, $direction, $offset, $max);

		$users = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($users)) {
			$sql    = WV_SQLEx::getInstance();
			$query  = 'SELECT id '.
				'FROM #_wv16_users u '.
				'LEFT JOIN #_wv16_user_groups ug ON u.id = ug.user_id '.
				'WHERE group_id = ? ORDER BY '.$orderBy.' '.$direction;

			if ($offset > 0 || $max < 0) {
				$max    = $max < 0 ? '18446744073709551615' : (int) $max;
				$query .= ' LIMIT '.$offset.','.$max;
			}

			$users = $sql->getArray($query, $groupID, '#_');
			$cache->set($namespace, $cacheKey, $users);
		}

		$result = array();

		foreach ($users as $userID) {
			$result[$userID] = _WV16_User::getInstance($userID);
		}

		return $result;
	}

	public static function getAllGroups($orderBy = 'title', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = WV_Cache::generateKey('groups_by', $orderBy, $direction, $offset, $max);

		$groups = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($groups)) {
			$sql    = WV_SQLEx::getInstance();
			$query  = 'SELECT id FROM #_wv16_groups WHERE 1 ORDER BY '.$orderBy.' '.$direction;

			if ($offset > 0 || $max < 0) {
				$max    = $max < 0 ? '18446744073709551615' : (int) $max;
				$query .= ' LIMIT '.$offset.','.$max;
			}

			$groups = $sql->getArray($query, array(), '#_');
			$cache->set($namespace, $cacheKey, $groups);
		}

		$result = array();

		foreach ($groups as $groupID) {
			$result[$groupID] = _WV16_Group::getInstance($groupID);
		}

		return $result;
	}

	public static function isLoggedIn() {
		$userID = rex_session('frontenduser', 'int', self::ANONYMOUS);
		return $userID > 0;
	}

	public static function getCurrentUser() {
		try {
			$userID = rex_session('frontenduser', 'int', self::ANONYMOUS);
			return $userID < 0 ? null : _WV16_User::getInstance($userID);
		}
		catch (Exception $e) {
			return null;
		}
	}

	public static function register($login, $password, $userType = null) {
		return _WV16_User::register($login, $password, $userType);
	}

	/**
	 * @param string $login
	 * @param string $password
	 * @return _WV16_User
	 */
	public static function login($login, $password) {
		$userObj = self::getUser($login);

		if ($userObj->isInGroup(_WV16_Group::GROUP_ACTIVATED) && self::checkPassword($userObj, $password)) {
			self::loginUser($userObj);
			return $userObj;
		}
		else {
			throw new WV16_Exception('This user is not yet activated.', self::ERR_USER_NOT_ACTIVATED);
		}
	}

	public static function loginUser(_WV16_User $user) {
		session_regenerate_id(); // Session-Fixation verhindern
		rex_set_session('frontenduser', $user->getID());
	}

	public static function logout()
	{
		session_destroy();
	}

	public static function getUser($login) {
		$sql       = WV_SQLEx::getInstance();
		$login     = strtolower(trim($login));
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.users.mappings';
		$cacheKey  = WV_Cache::generateKey('id_for', $login);

		$userID = $cache->get($namespace, $cacheKey, -1);

		if ($userID < 0) {
			$userID = $sql->saveFetch('id', 'wv16_users', 'deleted = 0 AND LOWER(login) = ?', $login);

			if ($userID === false) {
				throw new WV16_Exception('User unknown', self::ERR_USER_UNKNOWN);
			}

			$cache->set($namespace, $cacheKey, $userID);
		}

		return _WV16_User::getInstance($userID);
	}

	public static function checkPassword(_WV16_User $user, $password) {
		$password = trim($password);
		return sha1($user->getId().$password.$user->getRegistered()) === $user->getPasswordHash();
	}

	public static function isProtected($object, $objectType = null, $inherit = true) {
		list($objectID, $objectType) = _WV16_FrontendUser::identifyObject($object, $objectType);

		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.rights';
		$cacheKey  = WV_Cache::generateKey('is_protected', $objectID, $objectType, $inherit);
		$canAccess = $cache->get($namespace, $cacheKey, null);

		if (is_bool($canAccess)) {
			return $canAccess;
		}

		$sql = WV_SQLEx::getInstance();

		// Prüfen, ob es sich, wenn wir einen Artikel haben, es sich
		// gleichzeitig auch um eine Kategorie handelt.

		$isStartpage = $sql->saveFetch('startpage', 'article', 'id = ?', $objectID) && $objectType == _WV16_FrontendUser::TYPE_ARTICLE;

		// Wollen wir wirklich nur die Rechte für dieses eine Objekt,
		// egal, ob es vererbte Rechte gibt?

		if (!$inherit) {
			$privileges = $sql->count('wv16_rights', 'object_id = ? AND object_type = ?', array($objectID, $objectType));
			$cache->set($namespace, $cacheKey, $privileges > 0);
			return $privileges > 0;
		}

		// Die Berechtigung für dieses Objekt allein abrufen (explizite Rechte?)

		$privileges = $sql->saveFetch('*', 'wv16_rights', 'object_id = ? AND object_type = ?', array($objectID, $objectType));

		if (!empty($privileges)) {
			$cache->set($namespace, $cacheKey, true);
			return true;
		}

		// Wenn es sich um eine Datei handelt, gibt es keine Vererbung. Und wenn
		// es dann keine Rechte für die Datei gibt, ist das Objekt auch nicht
		// geschützt.

		if ($objectType == _WV16_FrontendUser::TYPE_MEDIUM) {
			$cache->set($namespace, $cacheKey, false);
			return false;
		}

		// Wir brauchen jetzt das Elternelement. Bei einem Artikel ist das die
		// ihn beinhaltende Kategorie, bei einer Kategorie die Elternkategorie.
		// Wenn es sich um eine Startseite einer Kategorie handelt, ist die
		// Elternkategorie logischerweise direkt "der Artikel selbst".

		$parentCategory = $isStartpage ? $objectID : $sql->saveFetch('re_id', 'article', 'id = ?', $objectID);

		if ($parentCategory == 0) {
			$cache->set($namespace, $cacheKey, false);
			return false; // Artikel/Kategorie der obersten Ebene, da generell alle Objekte erlaubt sind, hören wir hier auf.
		}

		return self::isProtected((int) $parentCategory, _WV16_FrontendUser::TYPE_CATEGORY);
	}

	public static function protect($object, $objectType = null, $loginArticle = null, $accessDeniedArticle = null) {
		global $REX;

		if (self::isProtected($object, $objectType)) {
			$user = WV16_Users::getCurrentUser();
			$url  = WV_Redaxo::getBaseUrl(true);

			if (!$user) {
				rex_set_session('frontenduser_target_url', $url);

				if ($loginArticle === null) {
					$loginArticle = self::getConfig('articles_login', $REX['NOTFOUND_ARTICLE_ID']);
				}

				WV_Redaxo::redirect($loginArticle);
			}

			$access = $user && $user->canAccess($object, $objectType);

			if (!$access) {
				rex_set_session('frontenduser_target_url', $url);

				if ($accessDeniedArticle == null) {
					$accessDeniedArticle = self::getConfig('articles_accessdenied', $REX['NOTFOUND_ARTICLE_ID']);
				}

				WV_Redaxo::redirect($accessDeniedArticle);
			}
		}
	}

	public static function generateConfirmationCode($login) {
		return substr(md5($login.mt_rand()), 0, 20);
	}

	public static function findByConfirmationCode($code) {
		$where = 'confirmation_code = "'.preg_replace('#[^a-z0-9]#i', '', $login).'"';
		$users = self::getAllUsers($where, 'id', 'asc', 0, 1);
		return empty($users) ? null : reset($users);
	}

	public static function isReadOnlySet($setID) {
		return _WV16_User::isReadOnlySet($setID);
	}

	public static function replaceAttributes($text, WV16_User $user) {
		$matches = array();
		preg_match_all('/#([a-z0-9_.,;:+~§$%&-]+)#/i', $text, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$attributeName = strtolower($match[1]);
			$replacement   = '';

			try {
				$value = $user->getValue($attributeName);
				if ($value === null) continue;

				$replacement = $value->getValue();

				if (is_array($replacement)) {
					$replacement = implode(', ', $replacement);
				}
			}
			catch (Exception $e) {
				// Eingabefehler, Tippfehler, Random Noise -> pass...
			}

			$text = str_replace($match[0], $replacement, $text);
		}

		return $text;
	}
}
