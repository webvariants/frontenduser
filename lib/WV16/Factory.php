<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_Factory {
	public static function getUser($login) {
		$sql       = WV_SQL::getInstance();
		$login     = strtolower(trim($login));
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.users.mappings';
		$cacheKey  = sly_Cache::generateKey('id_for', $login);

		$userID = $cache->get($namespace, $cacheKey, -1);

		if ($userID < 0) {
			$userID = $sql->fetch('id', 'wv16_users', 'deleted = 0 AND LOWER(login) = ?', $login);

			if ($userID === false) {
				throw new WV16_Exception('User unknown');
			}

			$cache->set($namespace, $cacheKey, $userID);
		}

		return _WV16_User::getInstance($userID);
	}

	public static function getUserByID($id) {
		return _WV16_User::getInstance($id);
	}

	public static function getGroup($name) {
		return _WV16_Group::getInstance($name);
	}

	public static function getAttribute($name) {
		$all = WV16_Provider::getAttributes();

		if (!isset($all[$name])) {
			throw new WV16_Exception('Attribute '.$name.' could not be found.');
		}

		return $all[$name];
	}

	public static function getUserType($name) {
		$all = WV16_Provider::getUserTypes();

		if (!isset($all[$name])) {
			throw new WV16_Exception('User type '.$name.' could not be found.');
		}

		return $all[$name];
	}
}
