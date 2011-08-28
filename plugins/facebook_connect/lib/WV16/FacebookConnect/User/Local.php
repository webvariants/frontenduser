<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_User_Local extends _WV16_User {
	private static $instances = array();

	/**
	 * @return WV16_FacebookConnect_User_Local  der entsprechende Benutzer
	 */
	public static function getInstance($localID) {
		$localID = (int) $localID;

		if (empty(self::$instances[$localID])) {
			$user = parent::getInstance($localID);

			self::$instances[$localID] = new self($user);
		}

		return self::$instances[$localID];
	}

	protected function __construct(_WV16_User $user) {
		foreach ($user as $var => $value) {
			$this->$$var = $value;
		}
	}

	public function isRegistered() {
		return true;
	}
}
