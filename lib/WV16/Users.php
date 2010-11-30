<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_Users extends _WV16_DataHandler {
	const ANONYMOUS              = 0;
	const ERR_USER_UNKNOWN       = 1;
	const ERR_INVALID_LOGIN      = 2;
	const ERR_USER_NOT_ACTIVATED = 3;

	public static function clearCache() {
		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);
	}

	public static function loginExists($login) {
		return _WV16_User::exists($login);
	}

	public static function getConfig($name, $default = null) {
		$value = WV8_Settings::getValue('frontenduser', $name);
		return empty($value) ? $default : $value; // Wenn leere Felder abgespeichert werden, sind sie ja nicht NULL
	}

	public static function setConfig($name, $value) {
		try {
			$setting = _WV8_Setting::getInstance('frontenduser', $name);
			$setting->setValue($value, WV_Sally::clang());
			$setting->update();

			return true;
		}
		catch (Exception $e) {
			return false;
		}
	}

	public static function getTotalUsers($where = '1') {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('total_users', $where);
		$total     = $cache->get($namespace, $cacheKey, -1);

		if ($total < 0) {
			$sql   = WV_SQLEx::getInstance();
			$total = $sql->count('wv16_users', $where);
			$total = $total === false ? -1 : (int) $total;
			$cache->set($namespace, $cacheKey, $total);
		}

		return $total;
	}

	// $max < 0 für unendlich
	public static function getAllUsers($where, $orderBy = 'id', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('users_by', $where, $orderBy, $direction, $offset, $max);

		$users = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($users)) {
			$sql    = WV_SQLEx::getInstance();
			$query  = 'SELECT id FROM ~wv16_users WHERE '.$where.' ORDER BY '.$orderBy.' '.$direction;
			$max    = $max < 0 ? '18446744073709551615' : (int) $max;
			$query .= ' LIMIT '.$offset.','.$max;

			$users = $sql->getArray($query, array(), '~');
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
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$groupID   = _WV16_FrontendUser::getIDForGroup($group, false);
		$cacheKey  = sly_Cache::generateKey('users_by', $group, $orderBy, $direction, $offset, $max);

		$users = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($users)) {
			$sql    = WV_SQLEx::getInstance();
			$query  = 'SELECT id '.
				'FROM ~wv16_users u '.
				'LEFT JOIN ~wv16_user_groups ug ON u.id = ug.user_id '.
				'WHERE group_id = ? ORDER BY '.$orderBy.' '.$direction;

			if ($offset > 0 || $max < 0) {
				$max    = $max < 0 ? '18446744073709551615' : (int) $max;
				$query .= ' LIMIT '.$offset.','.$max;
			}

			$users = $sql->getArray($query, $groupID, '~');
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
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('groups_by', $orderBy, $direction, $offset, $max);

		$groups = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($groups)) {
			$sql    = WV_SQLEx::getInstance();
			$query  = 'SELECT id FROM ~wv16_groups WHERE 1 ORDER BY '.$orderBy.' '.$direction;

			if ($offset > 0 || $max < 0) {
				$max    = $max < 0 ? '18446744073709551615' : (int) $max;
				$query .= ' LIMIT '.$offset.','.$max;
			}

			$groups = $sql->getArray($query, array(), '~');
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

		if ($userObj->isActivated() && self::checkPassword($userObj, $password)) {
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
		rex_register_extension_point('WV16_LOGIN', $user);
	}

	public static function logout() {
		$user = self::getCurrentUser();

		if ($user) {
			session_destroy();
			rex_register_extension_point('WV16_LOGOUT', $user);
		}
	}

	public static function getUser($login) {
		$sql       = WV_SQLEx::getInstance();
		$login     = strtolower(trim($login));
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.users.mappings';
		$cacheKey  = sly_Cache::generateKey('id_for', $login);

		$userID = $cache->get($namespace, $cacheKey, -1);

		if ($userID < 0) {
			$userID = $sql->safeFetch('id', 'wv16_users', 'deleted = 0 AND LOWER(login) = ?', $login);

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

	public static function generatePassword($salt = null) {
		if ($salt === null) {
			$current = self::getCurrentUser();
			$salt    = $current ? $current->getLogin() : rand();
		}

		return substr(md5($salt.mt_rand()), 0, 8);
	}

	public static function generateConfirmationCode($login) {
		return substr(md5($login.mt_rand()), 0, 20);
	}

	public static function findByConfirmationCode($code) {
		$where = 'confirmation_code = "'.preg_replace('#[^a-z0-9]#i', '', $code).'"';
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

			switch ($attributeName) {
				case 'login':
					$replacement = $user->getLogin();
					break;

				case 'confirmation_code':
				case 'code':
				case 'conf_code':
				case 'ccode':
					$replacement = $user->getConfirmationCode();
					break;

				case 'registered':
					$replacement = strftime('%d.%m.%Y %H:%M', strtotime($user->getRegistered()));
					break;

				default:
					try {
						$value       = $user->getValue($attributeName);
						$replacement = $value->getValue();

						if (is_array($replacement)) {
							$replacement = implode(', ', $replacement);
						}
					}
					catch (Exception $e) {
						// Eingabefehler, Tippfehler, Random Noise -> pass...
					}
			}

			$text = str_replace($match[0], $replacement, $text);
		}

		return $text;
	}
}
