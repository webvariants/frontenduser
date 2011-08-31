<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FriendConnect_User_FriendConnect implements WV16_User, WV16_FriendConnect_User_Interface {
	private $api;
	private $gfcID;
	private $me;

	private static $instance;

	/**
	 * @return WV16_FriendConnect_User_FriendConnect  der entsprechende Benutzer
	 */
	public static function getInstance() {
		if (!WV16_FriendConnect::isLoggedIn()) {
			throw new WV16_Exception('Cannot get FriendConnect user when no one is logged in.');
		}

		if (empty(self::$instance)) self::$instance = new self();
		return self::$instance;
	}

	protected function __construct() {
		$this->api   = WV16_FriendConnect::getAPI();
		$this->gfcID = WV16_FriendConnect::getCurrentUserID();
		$this->me    = null;
	}

	public function getLogin() {
		return 'gfc_'.$this->gfcID;
	}

	public function getID() {
		return $this->gfcID;
	}

	public function getFriendConnectID() {
		return $this->gfcID;
	}

	public function isRegistered() {
		return WV16_FriendConnect::isRegistered();
	}

	public function register($confirmed = true, $activated = true) {
		if ($this->isRegistered()) {
			throw new WV16_Exception('User is already registered.');
		}

		$id   = $this->getLogin();
		$pass = sly_Util_String::getRandomString(30, 30);
		$type = WV16_FriendConnect::getUserType();
		$user = WV16_Users::register($id, $pass, $type);
		$me   = $this->getMe();
		$urls = array();

		foreach ($me->urls as $url) {
			$urls[] = json_encode(array(isset($url['linkText']) ? $url['linkText'] : '', $url['value'], $url['type']));
		}

		$user->setConfirmed($confirmed);
		$user->setActivated($activated);
		$user->setValue('gfc_id', $me->id);
		$user->setValue('gfc_displayname', $me->displayName);
		$user->setValue('gfc_name', $me->name);
		$user->setValue('gfc_thumbnail', $me->thumbnailUrl);
		$user->setValue('gfc_urls', implode("\n", $urls));
		$user->update();

		return WV16_FriendConnect_User::getInstance($this->gfcID);
	}

	public function getMe() {
		if ($this->me === null) {
			$this->me = $this->api->getMe();
		}

		return $this->me;
	}

	public function getDisplayName() { return $this->getMe()->displayName;  }
	public function getName()        { return $this->getMe()->name;         }
	public function getThumbnail()   { return $this->getMe()->thumbnailUrl; }
	public function getURLs()        { return $this->getMe()->urls;         }

	public function getValue($attribute, $default = null) {
		return $default;
	}

	public function setValue($attribute, $value) {
		trigger_error('Do not call setValue() on Facebook users, it\'s useless.', E_USER_WARNING);
		return $value;
	}
}
