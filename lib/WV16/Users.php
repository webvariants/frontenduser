<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_Users {
	const ANONYMOUS = 0;

	public static function clearCache($params = array()) {
		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);

		return isset($params['subject']) ? $params['subject'] : true;
	}

	public static function loginExists($login) {
		return _WV16_User::exists($login);
	}

	public static function getConfig($namespace, $name, $default = null) {
		$value = WV8_Settings::getValue('frontenduser.'.$namespace, $name);
		return empty($value) ? $default : $value;
	}

	public static function isLoggedIn() {
		$userID = sly_Util_Session::get('frontenduser', 'int', self::ANONYMOUS);
		return $userID > 0;
	}

	public static function register($login, $password, $userType = null) {
		return _WV16_User::register($login, $password, $userType);
	}

	/**
	 * @param  string $login
	 * @param  string $password
	 * @return _WV16_User
	 */
	public static function login($login, $password, $allowNonConfirmed = false, $allowNonActivated = false) {
		$userObj = WV16_Factory::getUser($login);

		if (!$userObj->isActivated() && !$allowNonActivated) {
			throw new WV16_Exception('This account has not yet been activated.');
		}

		if (!$userObj->isConfirmed() && !$allowNonConfirmed) {
			throw new WV16_Exception('This account has not yet been confirmed.');
		}

		if (!$userObj->checkPassword($password)) {
			throw new WV16_Exception('Bad credentials given.');
		}

		self::loginUser($userObj);
		return $userObj;
	}

	public static function loginUser(_WV16_User $user) {
		sly_Util_Session::regenerate_id(); // Session-Fixation verhindern
		sly_Util_Session::set('frontenduser', $user->getID());
		sly_Core::dispatcher()->notify('WV16_LOGIN', $user);
	}

	public static function logout() {
		$user = self::getCurrentUser();

		if ($user) {
			sly_Util_Session::set('frontenduser', self::ANONYMOUS);
			session_destroy();
			sly_Core::dispatcher()->notify('WV16_LOGOUT', $user);
		}
	}

	/**
	 * @return _WV16_User  or null if no user is logged in
	 */
	public static function getCurrentUser() {
		try {
			$userID = sly_Util_Session::get('frontenduser', 'int', self::ANONYMOUS);
			return $userID <= 0 ? null : _WV16_User::getInstance($userID);
		}
		catch (Exception $e) {
			return null;
		}
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
		$users = WV16_Provider::getUsers($where, 'id', 'asc', 0, 1);

		return empty($users) ? null : reset($users);
	}

	public static function isReadOnlySet($setID) {
		return _WV16_Service_Set::isReadOnlySet($setID);
	}

	public static function replaceAttributes($text, WV16_User $user, $prefix = '') {
		$matches = array();
		$prefix  = preg_quote($prefix, '/');

		preg_match_all('/#'.$prefix.'([a-z0-9_.,;:+~§$%&-]+)#/i', $text, $matches, PREG_SET_ORDER);

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
						$replacement = $user->getValue($attributeName);

						if (is_array($replacement)) {
							$replacement = sly_Util_String::humanImplode(array_values($replacement));
						}
						elseif (is_bool($replacement)) {
							$replacement = $replacement ? 'ja' : 'nein';
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

	public static function syncYAML() {
		sly_Core::cache()->delete('frontenduser', 'attributes');
		sly_Core::cache()->delete('frontenduser', 'types');

		_WV16_Service_Attribute::loadAll();
		_WV16_Service_UserType::loadAll();
	}

	public static function isProtectedListener(array $params) {
		// if someone else has already decied that this file is protected, do nothing
		if ($params['subject']) return true;

		$file     = $params['file']; // e.g. "data/mediapool/foo.jpg"
		$basename = basename($file);

		// if the file is not stored in mediapool, it cannot be protected by FrontendUser
		if (!sly_Util_String::startsWith($file, 'data/mediapool')) return false;

		// find file in mediapool
		$fileObj = OOMedia::getMediaByFileName($basename);
		if ($fileObj === null) return false;

		// let the project decide whether the file is protected
		return sly_Core::dispatcher()->filter('WV16_IS_FILE_PROJECTED', false, compact('fileObj'));
	}
}
