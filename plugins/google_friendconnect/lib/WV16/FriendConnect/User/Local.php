<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FriendConnect_User_Local extends _WV16_User {
	private static $instances = array();

	/**
	 * @return WV16_FriendConnect_User_Local  der entsprechende Benutzer
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
			$this->$var = $value;
		}
	}

	public function isRegistered() {
		return true;
	}

	public static function register($login, $password, $userType = null) {
		trigger_error('Registering is only supported for non-local users.', E_USER_WARNING);
		return null;
	}

	public function getFriendConnectID() { return $this->getValue('gfc_id',         '');  }
	public function getDiplayName()      { return $this->getValue('gfc_displayname', ''); }
	public function getName()            { return $this->getValue('gfc_name', '');        }
	public function getThumbnail()       { return $this->getValue('gfc_thumbnail', '');   }

	public function getURLs() {
		$data = array_filter(explode("\n", $this->getValue('gfc_urls', '')));
		$urls = array();

		foreach ($data as $row) {
			$urls[] = json_decode($row, true);
		}

		return $urls;
	}
}
