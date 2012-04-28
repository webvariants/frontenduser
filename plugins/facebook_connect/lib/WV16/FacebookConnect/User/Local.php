<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_User_Local extends _WV16_User implements WV16_FacebookConnect_User_Interface {
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

	public function getFacebookID() { return $this->getFacebookValue('id');         }
	public function getName()       { return $this->getFacebookValue('name');       }
	public function getFirstname()  { return $this->getFacebookValue('first_name'); }
	public function getLastname()   { return $this->getFacebookValue('last_name');  }
	public function getLink()       { return $this->getFacebookValue('link');       }
	public function getUsername()   { return $this->getFacebookValue('username');   }
	public function getEMail()      { return $this->getFacebookValue('email');      }
	public function getGender()     { return $this->getFacebookValue('gender');     }
	public function getTimezone()   { return $this->getFacebookValue('timezone');   }
	public function getLocale()     { return $this->getFacebookValue('locale');     }
	public function isVerified()    { return $this->getFacebookValue('verified');   }

	protected function getFacebookValue($key) {
		static $mapping = null;

		if ($key === 'id') {
			$key = sly_Core::config()->get('frontenduser_fbconnect/id_attribute');
		}
		else {
			if ($mapping === null) {
				$mapping = sly_Core::config()->get('frontenduser_fbconnect/mapping', array());
			}

			$key = $mapping[$key];
		}

		return $this->getValue($key, '');
	}
}
