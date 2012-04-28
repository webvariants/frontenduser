<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_FacebookConnect {
	public static function clearCache($params = array()) {
		$cache = sly_Core::cache();
		$cache->flush('frontenduser.fbconnect', true);

		return isset($params['subject']) ? $params['subject'] : true;
	}

	public static function getUserTypes() {
		$types = sly_Core::config()->get('frontenduser_fbconnect/types', array());

		if (empty($types)) {
			throw new WV16_Exception('You must configure at least one usertype for Facebook users.');
		}

		return $types;
	}

	public static function getAppID() {
		return WV8_Settings::getValue('frontenduser.fbconnect', 'appid');
	}

	public static function getAppSecret() {
		return WV8_Settings::getValue('frontenduser.fbconnect', 'appsecret');
	}

	public static function getFacebook() {
		static $fb = null;

		if ($fb === null) {
			$fb = new WV16_FacebookConnect_API(array(
				'appId'  => self::getAppID(),
				'secret' => self::getAppSecret()
			));
		}

		return $fb;
	}

	public static function getCurrentUser() {
		$id = self::getCurrentUserID();
		return $id ? WV16_FacebookConnect_User::getInstance($id) : null;
	}

	public static function getCurrentUserID() {
		return self::isLoggedIn() ? self::getFacebook()->getUser() : null;
	}

	public static function isLoggedIn() {
		static $ok = null;

		if ($ok === null) {
			$fb = self::getFacebook();

			if ($fb->getSignedRequest() === null) {
				$ok = false;
			}
			else {
				try {
					$fb->api('/me');
					$ok = true;
				}
				catch (FacebookApiException $e) {
					$ok = false;
				}
			}
		}

		return $ok;
	}

	public static function isRegistered() {
		$id = self::getCurrentUserID();
		return $id && WV16_FacebookConnect_User::getLocalID($id) !== null;
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
