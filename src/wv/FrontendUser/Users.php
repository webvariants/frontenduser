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

	public static function onClearCache(array $params = array()) {
		$isSystem   = \sly_Core::getCurrentControllerName() === 'system';
		$controller = \sly_Core::getCurrentController();
		$selective  = $isSystem && method_exists($controller, 'isCacheSelected');
		$clearData  = !$selective || $controller->isCacheSelected('fu-data');
		$rebuild    = !$selective || $controller->isCacheSelected('fu-rebuild');

		if ($clearData) {
			self::clearCache();
		}

		if ($rebuild) {
			self::rebuildUserdata(array('subject' => true));
		}

		return isset($params['subject']) ? $params['subject'] : true;
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
		\sly_Util_Session::set('frontenduser', $user->getID());
		\sly_Util_Session::set('frontenduser_groups', $user->getGroupNames());
		\sly_Core::dispatcher()->notify('WV16_LOGIN', $user);
	}

	public static function logout() {
		$user = self::getCurrentUser();

		\sly_Util_Session::set('frontenduser', self::ANONYMOUS);
		\sly_Util_Session::set('frontenduser_groups', array());
		session_destroy();

		if ($user) {
			\sly_Core::dispatcher()->notify('WV16_LOGOUT', $user);
		}
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

	public static function isProtectedListener(array $params) {
		// if someone else has already decied that this file is protected, do nothing
		if ($params['subject']) return true;

		$file     = $params['file']; // e.g. "data/mediapool/foo.jpg" or (with image_resize) "data/mediapool/600w__foo.jpg"
		$basename = basename($file);

		// if the file is not stored in mediapool, it cannot be protected by FrontendUser
		if (!\sly_Util_String::startsWith($file, 'data/mediapool')) return false;

		// image_resize request?
		if (\sly_Service_Factory::getAddOnService()->isAvailable('image_resize')) {
			$result = \A2_Extensions::parseFilename($basename);
			if ($result) $basename = $result['filename']; // "600w__foo.jpg" -> "foo.jpg"
		}

		// find file in mediapool
		$fileObj = \sly_Util_Medium::findByFilename($basename);
		if ($fileObj === null) return false;

		// let the project decide whether the file is protected
		return \sly_Core::dispatcher()->filter('WV16_IS_FILE_PROTECTED', false, compact('fileObj'));
	}

	public static function rebuildUserdata(array $params) {
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

		// done :-)
		return $params['subject'];
	}

	public static function initMenu(array $params) {
		$user = \sly_Util_User::getCurrentUser();

		if ($user) {
			$isAdmin = $user->isAdmin();
			$nav     = \sly_Core::getLayout()->getNavigation();
			$exports = \sly_Core::config()->get('frontenduser/exports', null);

			if ($isAdmin || $user->hasRight('frontenduser', 'users')) {
				$page = $nav->addPage('addon', 'frontenduser', t('frontenduser_title'));
				$page->addSubpage('frontenduser', t('users'));

				if ($isAdmin || $user->hasRight('frontenduser', 'groups')) {
					$page->addSubpage('frontenduser_groups', 'Gruppen');
				}

				if ($exports && ($isAdmin || $user->hasRight('frontenduser', 'groups'))) {
					$page->addSubpage('frontenduser_exports', 'Export');
				}
			}
		}
	}

	/**
	 * SLY_SYSTEM_CACHES
	 *
	 * @param array $params  event parameters
	 */
	public static function systemCacheList(array $params) {
		$select     = $params['subject'];
		$selected   = $select->getValue();
		$selected[] = 'fu-data';

		if (\sly_Core::isDeveloperMode()) {
			$selected[] = 'fu-rebuild';
		}

		$select->addValue('fu-data',    'Benutzerdaten-Cache leeren');
		$select->addValue('fu-rebuild', 'Benutzerdaten validieren & neu aufbauen');
		$select->setSelected($selected);

		return $select;
	}
}
