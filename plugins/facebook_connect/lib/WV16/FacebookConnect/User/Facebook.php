<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_User_Facebook implements WV16_User, WV16_FacebookConnect_User_Interface {
	private $facebook;
	private $facebookID;
	private $fbdata;

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
		$this->fbdata     = null;
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

	public function register($confirmed = true, $activated = true) {
		if ($this->isRegistered()) {
			throw new WV16_Exception('User is already registered.');
		}

		$id   = $this->getLogin();
		$pass = sly_Util_String::getRandomString(30, 30);
		$type = WV16_FacebookConnect::getUserType();
		$user = WV16_Users::register($id, $pass, $type);

		$user->setConfirmed($confirmed);
		$user->setActivated($activated);

		// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

		foreach ($this->getFacebookDetails() as $name => $value) {
			if ($name === 'facebook_email') {
				$user->setValue('email', $value);
			}
			else {
				$user->setValue($name, $value);
			}
		}

		$user->update();

		return WV16_FacebookConnect_User::getInstance($this->facebookID);
	}

	public function getFacebookDetails() {
		if ($this->fbdata === null) {
			$data         = $this->facebook->api('/me');
			$this->fbdata = array();

			foreach ($data as $key => $value) {
				if ($key === 'updated_time') continue;
				$key = 'facebook_'.$key;
				$this->fbdata[$key] = $value;
			}
		}

		return $this->fbdata;
	}

	public function getFacebookID() { return $this->getValue('facebook_id',         '');    }
	public function getName()       { return $this->getValue('facebook_name',       '');    }
	public function getFirstname()  { return $this->getValue('facebook_first_name', '');    }
	public function getLastname()   { return $this->getValue('facebook_last_name',  '');    }
	public function getLink()       { return $this->getValue('facebook_link',       '');    }
	public function getUsername()   { return $this->getValue('facebook_username',   '');    }
	public function getEMail()      { return $this->getValue('facebook_email',      '');    } // only with scope:email!
	public function getGender()     { return $this->getValue('facebook_gender',     '');    }
	public function getTimezone()   { return $this->getValue('facebook_timezone',   '');    }
	public function getLocale()     { return $this->getValue('facebook_locale',     '');    }
	public function isVerified()    { return $this->getValue('facebook_verified',   false); }

	public function getValue($attribute, $default = null) {
		$data = $this->getFacebookDetails();
		return isset($data[$attribute]) ? $data[$attribute] : $default;
	}

	public function setValue($attribute, $value) {
		trigger_error('Do not call setValue() on Facebook users, it\'s useless.', E_USER_WARNING);
		return $value;
	}
}
