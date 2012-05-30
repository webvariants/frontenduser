<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_Facebook {
	/**
	 * @return array
	 */
	public static function getUserTypes() {
		$types = sly_Core::config()->get('frontenduser_facebook/types', array());

		if (empty($types)) {
			throw new WV16_Exception('You must configure at least one usertype for Facebook users.');
		}

		return $types;
	}

	/**
	 * @return string
	 */
	public static function getAppID() {
		return WV8_Settings::getValue('frontenduser.facebook', 'appid');
	}

	/**
	 * @return string
	 */
	public static function getAppSecret() {
		return WV8_Settings::getValue('frontenduser.facebook', 'appsecret');
	}

	/**
	 * @return Facebook
	 */
	public static function getFacebook() {
		static $instance;

		if (!$instance) {
			$instance = new Facebook(array(
				'appId'  => self::getAppID(),
				'secret' => self::getAppSecret()
			));
		}

		return $instance;
	}

	/**
	 * @return WV16_Facebook_Cache
	 */
	public static function getFacebookCache($namespace, $lifetime) {
		return new WV16_Facebook_Cache(array(
			'appId'  => self::getAppID(),
			'secret' => self::getAppSecret()
		), $namespace, $lifetime);
	}

	public static function getCurrentUserID() {
		$id = self::getFacebook()->getUser();
		return $id ? $id : null;
	}

	public static function getCurrentUser() {
		$id = self::getCurrentUserID();
		return $id ? WV16_Facebook_User::getInstance($id) : null;
	}

	public static function isLoggedIn() {
		return self::getCurrentUserID() !== null;
	}

	public static function isRegistered() {
		$id = self::getCurrentUserID();
		return $id && WV16_Facebook_User::getLocalID($id) !== null;
	}

	public static function getAuthToken() {
		$fb    = self::getFacebook();
		$token = $fb->getSignedRequest();

		return $token ? $token : null;
	}

	public static function getAuthData() {
		$token = self::getAuthToken();
		$key   = 'fbsr_'.self::getAppID();

		return $token ? array($key => $token) : $token;
	}

	public static function addRoute(array $params) {
		$router = $params['subject'];

		$router->addRoute('/fbrt', array(
			'controller' => 'FacebookRealtime',
			'action'     => 'index'
		));

		return $router;
	}
}
