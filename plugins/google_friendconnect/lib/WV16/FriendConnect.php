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

	public static function getAPI() {
		static $gfc = null;

		if ($gfc === null) {
			$gfc = new WV16_FriendConnect_API(self::getSiteID(), self::getAuthToken());
		}

		return $gfc;
	}

	public static function getCurrentUser() {
		if (!self::isLoggedIn()) return null;
		return WV16_FriendConnect_User::getInstance(self::getCurrentUserID());
	}

	public static function getCurrentUserID() {
		$gfc = self::getAPI();
		$id  = $gfc->getUser();
		return $id ? $id : null;
	}

	public static function isLoggedIn() {
		return self::getCurrentUserID() !== null;
	}

	public static function isRegistered() {
		if (!self::isLoggedIn()) return false;

		$id    = self::getCurrentUserID();
		$users = WV16_Provider::getUsersWithAttribute('gfc_id', self::getUserType(), 1, $id);

		return !empty($users);
	}
}
