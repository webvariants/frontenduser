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

abstract class Factory {
	public static function getUser($login) {
		$sql       = \WV_SQL::getInstance();
		$login     = strtolower(trim($login));
		$cache     = \sly_Core::cache();
		$namespace = 'frontenduser.users.mappings';
		$cacheKey  = \sly_Cache::generateKey('id_for', $login);

		$userID = $cache->get($namespace, $cacheKey, -1);

		if ($userID < 0) {
			$userID = $sql->fetch('id', 'wv16_users', 'deleted = 0 AND LOWER(login) = ?', $login);

			if ($userID === false) {
				throw new Exception('User unknown');
			}

			$cache->set($namespace, $cacheKey, $userID);
		}

		return User::getInstance($userID);
	}

	public static function getUserByID($id) {
		return User::getInstance($id);
	}

	public static function getGroup($name) {
		return Group::getInstance($name);
	}

	public static function getAttribute($name) {
		$all = Provider::getAttributes();

		if (!isset($all[$name])) {
			throw new Exception('Attribute '.$name.' could not be found.');
		}

		return $all[$name];
	}

	public static function getUserType($name) {
		$all = Provider::getUserTypes();

		if (!isset($all[$name])) {
			throw new Exception('User type '.$name.' could not be found.');
		}

		return $all[$name];
	}
}
