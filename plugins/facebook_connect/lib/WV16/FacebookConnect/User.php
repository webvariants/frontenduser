<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_FacebookConnect_User {
	/**
	 * @return WV16_FacebookConnect_User  der entsprechende Benutzer
	 */
	public static function getInstance($facebookID) {
		if ($facebookID == 0) {
			return null;
		}

		$localUserID = self::getLocalID($facebookID);

		if ($localUserID === null) {
			return WV16_FacebookConnect_User_Facebook::getInstance();
		}

		return WV16_FacebookConnect_User_Local::getInstance($localUserID);
	}

	public static function getLocalID($facebookID) {
		$users = WV16_Provider::getUsersWithAttribute('facebook_id', WV16_FacebookConnect::getUserType(), 1, $facebookID);
		if (empty($users)) return null;
		$user = reset($users);
		return $user->getId();
	}
}
