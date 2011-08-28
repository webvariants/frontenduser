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
		if (!self::isLoggedIn()) return null;
		return WV16_FacebookConnect_User::getInstance(self::getCurrentUserID());
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
}
