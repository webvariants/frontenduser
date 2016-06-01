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

abstract class Users {
	const ANONYMOUS = 0;

	const ERR_NOT_ACTIVATED   = 1;
	const ERR_NOT_CONFIRMED   = 2;
	const ERR_BAD_CREDENTIALS = 3;
	const ERR_LOGIN_TAKEN     = 4;
	const ERR_EMPTY_LOGIN     = 5;
	const ERR_PWD_TOO_SHORT   = 6;
	const ERR_PWD_TOO_WEAK    = 7;

	public static function clearCache() {
		$cache = \sly_Core::cache();
		$cache->flush('frontenduser', true);
	}

	public static function loginExists($login) {
		return User::exists($login);
	}

	public static function isLoggedIn($checkID = false) {
		$userID = \sly_Util_Session::get('frontenduser', 'int', self::ANONYMOUS);
		if ($userID <= 0) return false;

		return $checkID ? self::getCurrentUser() !== null : true;
	}

	public static function register($login, $password, $userType = null) {
		return User::register($login, $password, $userType);
	}

	/**
	 * @param  string $login
	 * @param  string $password
	 * @return User
	 */
	public static function login($login, $password, $allowNonConfirmed = false, $allowNonActivated = false) {
		$userObj = Factory::getUser($login);

		if (!$userObj->isActivated() && !$allowNonActivated) {
			throw new Exception('This account has not yet been activated.', self::ERR_NOT_ACTIVATED);
		}

		if (!$userObj->isConfirmed() && !$allowNonConfirmed) {
			throw new Exception('This account has not yet been confirmed.', self::ERR_NOT_CONFIRMED);
		}

		if (!$userObj->checkPassword($password)) {
			throw new Exception('Bad credentials given.', self::ERR_BAD_CREDENTIALS);
		}

		self::loginUser($userObj);
		return $userObj;
	}

	public static function loginUser(User $user) {
		\sly_Util_Session::regenerate_id(); // Session-Fixation verhindern
		self::setCurrentUser($user);
		\sly_Core::dispatcher()->notify('WV16_LOGIN', $user);
	}

	public static function logout() {
		$user = self::getCurrentUser();

		self::setCurrentUser(null);
		session_destroy();

		if ($user) {
			\sly_Core::dispatcher()->notify('WV16_LOGOUT', $user);
		}
	}

	public static function setCurrentUser(User $user = null) {
		\sly_Util_Session::set('frontenduser',        $user ? $user->getID()         : self::ANONYMOUS);
		\sly_Util_Session::set('frontenduser_groups', $user ? $user->getGroupNames() : array());
	}

	/**
	 * @return User  or null if no user is logged in
	 */
	public static function getCurrentUser() {
		try {
			$userID = \sly_Util_Session::get('frontenduser', 'int', self::ANONYMOUS);
			return $userID <= 0 ? null : User::getInstance($userID);
		}
		catch (\Exception $e) {
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
		$where = 'confirmation_code = "'.preg_replace('#[^a-z0-9]#i', '', $code).'" AND confirmation_code != ""';
		$users = Provider::getUsers($where, 'id', 'asc', 0, 1);

		return empty($users) ? null : reset($users);
	}

	public static function isReadOnlySet($setID) {
		return Service\SetService::isReadOnlySet($setID);
	}

	public static function replaceAttributes($text, User $user, $prefix = '') {
		return \WV_UserWorkflows_Helper::replaceAttributes($text, $user, $prefix);
	}

	public static function syncYAML() {
		\sly_Core::cache()->delete('frontenduser', 'attributes');
		\sly_Core::cache()->delete('frontenduser', 'types');

		Service\AttributeService::loadAll();
		Service\UserTypeService::loadAll();
	}

	public static function rebuildUserdata() {
		// refresh the attributes
		\sly_Core::cache()->delete('frontenduser', 'types');
		\sly_Core::cache()->delete('frontenduser', 'attributes');

		$allTypes      = Service\UserTypeService::loadAll(true);
		$allAttributes = Service\AttributeService::loadAll(true);

		$sql = \WV_SQL::getInstance();
		$sql->beginTransaction();

		// and here we go (re-using the implementation of some methods)
		$service = new Service\UserTypeService();
		$service->rebuild($allTypes);

		$service = new Service\AttributeService();
		$service->rebuild($allAttributes);

		$sql->commit();
	}
}
