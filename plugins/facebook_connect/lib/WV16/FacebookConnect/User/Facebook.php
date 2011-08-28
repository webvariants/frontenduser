<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_User_Facebook implements WV16_User {
	private $facebook;
	private $facebookID;

	private static $instance;

	/**
	 * @return WV16_FacebookConnect_User_Facebook  der entsprechende Benutzer
	 */
	public static function getInstance() {
		if (!WV16_FacebookConnect::isLoggedIn()) {
			throw new WV16_Exception('Cannot get Facebook user when no one is logged in.');
		}

		if (empty(self::$instance)) self::$instance = new self();
		return self::$instance;
	}

	protected function __construct() {
		$this->facebook   = WV16_FacebookConnect::getFacebook();
		$this->facebookID = WV16_FacebookConnect::getCurrentUserID();
	}

	public function getLogin() {
		return 'fb_'.$this->facebookID;
	}

	public function getID() {
		return $this->facebookID;
	}

	public function isRegistered() {
		return WV16_FacebookConnect::isRegistered();
	}

	public function register() {
		if (!self::isLoggedIn()) {
			throw new WV16_Exception('Cannot register when not logged in.');
		}

		if (self::isRegistered()) {
			throw new WV16_Exception('User is already registered.');
		}

		$id   = self::getCurrentUserID();
		$user = WV16_Users::register('fb_'.$id, $pass, self::getUserType());

		$user->setConfirmed($confirmed);
		$user->setActivated($activated);

		// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

		foreach ($valuesToStore as $name => $value) {
			$user->setValue($name, $value);
		}

		// Gruppen hinzufügen

		foreach ($groups as $group) {
			$user->addGroup($group);
		}

		$user->update();

		return !empty($users);
	}

	public function getValue($attribute, $default = null) {
		return $default;
	}

	public function setValue($attribute, $value) {
		trigger_error('Do not call setValue() on Facebook users, it\'s useless.', E_USER_WARNING);
		return $value;
	}
}
