<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_FriendConnect_User {
	/**
	 * @return WV16_FriendConnect_User  der entsprechende Benutzer
	 */
	public static function getInstance($gfcID) {
		if ($gfcID == 0) {
			return null;
		}

		$localUserID = self::getLocalID($gfcID);

		if ($localUserID === null) {
			return WV16_FriendConnect_User_FriendConnect::getInstance();
		}

		return WV16_FriendConnect_User_Local::getInstance($localUserID);
	}

	public static function getLocalID($gfcID) {
		$users = WV16_Provider::getUsersWithAttribute('gfc_id', WV16_FriendConnect::getUserType(), 1, $gfcID);
		if (empty($users)) return null;
		$user = reset($users);
		return $user->getId();
	}
}
