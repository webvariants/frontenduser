<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_Facebook_User {
	/**
	 * @return WV16_Facebook_User  der entsprechende Benutzer
	 */
	public static function getInstance($facebookID) {
		if ($facebookID == 0) {
			return null;
		}

		if ($facebookID === null) {
			return WV16_Facebook_User_Facebook::getInstance();
		}

		$localUserID = self::getLocalID($facebookID);

		if ($localUserID !== null) {
			return WV16_Facebook_User_Local::getInstance($localUserID);
		}

		try {
			return WV16_Facebook_User_Facebook::getInstance();
		}
		catch (Exception $e) {
			return null;
		}
	}

	public static function getLocalID($facebookID) {
		$types = WV16_Facebook::getUserTypes();
		$attr  = sly_Core::config()->get('frontenduser_facebook/id_attribute');
		$users = WV16_Provider::getUsersWithAttribute($attr, $types, 1, $facebookID);

		// this is ok
		if (empty($users)) {
			return null;
		}

		// this is not
		if (count($users) > 1) {
			trigger_error('Found more than one user for an unique Facebook ID. This is bad. Using the first.', E_USER_WARNING);
		}

		$user = reset($users);
		return $user->getId();
	}
}
