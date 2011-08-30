<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_FriendConnect {
	public static function clearCache($params = array()) {
		$cache = sly_Core::cache();
		$cache->flush('frontenduser.gfc', true);

		return isset($params['subject']) ? $params['subject'] : true;
	}

	public static function getAuthToken() {
		$cookieName = 'fcauth'.self::getSiteID();
		return isset($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : null;
	}

	public static function getUserType() {
		return 'gfc';
	}

	public static function getSiteID() {
		return WV8_Settings::getValue('frontenduser.gfc', 'siteid');
	}

	public static function getConsumerKey() {
		return WV8_Settings::getValue('frontenduser.gfc', 'consumerkey');
	}

	public static function getConsumerSecret() {
		return WV8_Settings::getValue('frontenduser.gfc', 'consumersecret');
	}

	public static function getAPI() {
		static $gfc = null;

		if ($gfc === null) {
			$gfc = new WV16_FriendConnect_API(self::getAuthToken());
		}

		return $gfc;
	}

	public static function getCurrentUser() {
		if (!self::isLoggedIn()) return null;
		return WV16_FriendConnect_User::getInstance(self::getCurrentUserID());
	}

	public static function getCurrentUserID() {
		$auth = self::getAuthToken();
		if (empty($auth)) return null;

		$gfc = self::getAPI();
		$me  = $gfc->getMe();
		return $me ? $me->id : null;
	}

	public static function isLoggedIn() {
		return self::getCurrentUserID() !== null;
	}

	public static function isRegistered() {
		$id = self::getCurrentUserID();
		return $id && WV16_FriendConnect_User::getLocalID($id) !== null;
	}
}
