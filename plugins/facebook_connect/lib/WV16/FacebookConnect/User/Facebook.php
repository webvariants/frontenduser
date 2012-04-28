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

	public function register($confirmed = true, $activated = true, $userType = null, $login = null) {
		if ($this->isRegistered()) {
			throw new WV16_Exception('User is already registered.');
		}

		$id    = $login === null ? $this->getLogin() : $login;
		$pass  = sly_Util_String::getRandomString(30, 30);
		$types = WV16_FacebookConnect::getUserTypes();

		if ($userType === null) {
			if (count($types) === 1) {
				$userType = reset($types);
			}
			else {
				throw new WV16_Exception('You must give a specific usertype, as there is more than one type configured.');
			}
		}
		elseif (!in_array($userType, $types)) {
			throw new WV16_Exception('This method may only be used to register Facebook accounts; invalid usertype "'.$userType.'" given.');
		}

		$user = WV16_Users::register($id, $pass, $userType);

		$user->setConfirmed($confirmed);
		$user->setActivated($activated);

		// copy as many information as possible
		// Pay attention to what fields are available, how they are mapped
		// and whether the current type has them assigned or not.
		$mapping    = sly_Core::config()->get('frontenduser_fbconnect/mapping', array());
		$attributes = array_keys(WV16_Provider::getAttributes($userType));

		foreach ($this->getFacebookDetails() as $name => $value) {
			// if this field should not be mapped, continue
			if (!isset($mapping[$name])) continue;

			// if this mapped field is not assigned to the chosen type, skip it as well
			$mapped = $mapping[$name];
			if (!in_array($mapped, $attributes)) continue;

			// if the value is complex (i.e. an array like 'location'), store it JSON encoded
			if (!is_scalar($value)) $value = json_encode($value);

			$user->setValue($mapped, $value);
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
				$this->fbdata[$key] = $value;
			}
		}

		return $this->fbdata;
	}

	public function getFacebookID() { return $this->getValue('id',         '');    }
	public function getName()       { return $this->getValue('name',       '');    }
	public function getFirstname()  { return $this->getValue('first_name', '');    }
	public function getLastname()   { return $this->getValue('last_name',  '');    }
	public function getLink()       { return $this->getValue('link',       '');    }
	public function getUsername()   { return $this->getValue('username',   '');    }
	public function getEMail()      { return $this->getValue('email',      '');    } // only with scope:email!
	public function getGender()     { return $this->getValue('gender',     '');    }
	public function getTimezone()   { return $this->getValue('timezone',   '');    }
	public function getLocale()     { return $this->getValue('locale',     '');    }
	public function isVerified()    { return $this->getValue('verified',   false); }

	public function getValue($attribute, $default = null) {
		$data = $this->getFacebookDetails();
		return isset($data[$attribute]) ? $data[$attribute] : $default;
	}

	public function setValue($attribute, $value) {
		trigger_error('Do not call setValue() on Facebook users, it\'s useless.', E_USER_WARNING);
		return $value;
	}
}
