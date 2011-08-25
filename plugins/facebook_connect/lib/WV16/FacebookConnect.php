<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_FacebookConnect {
	public static function clearCache($params = array()) {
		$cache = sly_Core::cache();
		$cache->flush('frontenduser.fbconnect', true);

		return isset($params['subject']) ? $params['subject'] : true;
	}

	public static function getUserType() {
		return 'fbconnect';
	}

	public static function getAppID() {
		return WV8_Settings::getValue('frontenduser.fbconnect', 'appid');
	}

	public static function getAppSecret() {
		return WV8_Settings::getValue('frontenduser.fbconnect', 'appsecret');
	}

	public static function getFacebook() {
		static $fb = null;

		if ($fb === null) {
			$fb = new Facebook(array(
				'appId'  => self::getAppID(),
				'secret' => self::getAppSecret()
			));
		}

		return $fb;
	}

	public static function getCurrentUser() {
		static $user = null;

		if (!self::isLoggedIn()) return null;

		if ($user === null) {
			$user = new WV16_FacebookConnect_User(self::getCurrentUserID());
		}

		return $user;
	}

	public static function getCurrentUserID() {
		$fb = self::getFacebook();
		$id = $fb->getUser();
		return $id ? $id : null;
	}

	public static function isLoggedIn() {
		return self::getCurrentUserID() !== null;
	}

	public static function isRegistered() {
		if (!self::isLoggedIn()) return false;

		$id    = self::getCurrentUserID();
		$users = WV16_Provider::getUsersWithAttribute('fb_id', self::getUserType(), 1, $id);

		return !empty($users);
	}

	public static function register() {
		if (!self::isLoggedIn()) {
			throw new WV16_Exception('Cannot register when not logged in.');
		}

		if (self::isRegistered()) {
			throw new WV16_Exception('User is already registered.');
		}

		$id   = self::getCurrentUserID();
		$user = WV16_Users::register('fb_'.$id, $pass, self::getUserType());

		$user->setConfirmed($confirmed);
		$user->setActivated($activated);

		// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

		foreach ($valuesToStore as $name => $value) {
			$user->setValue($name, $value);
		}

		// Gruppen hinzufügen

		foreach ($groups as $group) {
			$user->addGroup($group);
		}

		$user->update();

		return !empty($users);
	}
}
