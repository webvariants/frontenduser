<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_User extends _WV16_User {
	private static $instances = array();

	/**
	 * @return WV16_FacebookConnect_User  der entsprechende Benutzer
	 */
	public static function getInstance($userID) {
		$userID = (int) $userID;

		if ($userID <= 0) {
			return null;
		}

		$localUserID = self::getLocalID($userID);

		if ($userID === null) {
			return null;
		}

		if (empty(self::$instances[$userID])) {
			$callback = array(__CLASS__, '_getInstance');
			$instance = self::getFromCache('frontenduser.users', $localUserID, $callback, $localUserID);

			self::$instances[$userID] = $instance;
		}

		return self::$instances[$userID];
	}

	protected static function _getInstance($id) {
		return new self($id);
	}

	protected function __construct($id) {
		parent::__construct($id);
	}

	public static function getLocalID($facebookID) {
		$users = WV16_Provider::getUsersWithAttribute('fb_id', WV16_FacebookConnect::getUserType(), 1, $facebookID);
		if (empty($users)) return null;
		$user = reset($users);
		return $user->getValue('facebook_id');
	}
}
