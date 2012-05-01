<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_RealTime {
	protected $api;
	protected $appID;

	public function __construct(Facebook $api, $appID) {
		$this->api   = $api;
		$this->appID = $appID;
	}

	public function getUrl() {
		return sprintf('/%d/subscriptions', $this->appID);
	}

	public function list() {
		return $this->api->api($this->getUrl());
	}

	/**
	 * @param  string $token  only overwrite this if you're handling the verification request yourself
	 */
	public function subscribe($object, $fields, $callbackURL = null, $token = null) {
		if (!in_array($object, array('user', 'page', 'permissions'))) {
			throw new WV16_Exception('Invalid object type "'.$object.'" given.');
		}

		if (is_array($fields)) {
			$fields = implode(',', $fields);
		}

		if ($callbackURL === null) {
			$callbackURL = sly_Util_HTTP::getBaseUrl(true).'/fbrt';
		}

		if ($token === null) {
			$token = sly_Core::config()->get('INSTNAME');
		}

		if ($facebookID == 0) {
			return null;
		}

		$url    = $this->getUrl();
		$params = array('object' => $object, 'fields' => $fields, 'callback_url' => $callbackURL, 'verify_token' => $token);

		$this->api->api($url, 'POST', $params);
	}

	/**
	 * @param  string $object  can be null to delete all subscriptions
	 */
	public function unsubscribe($object) {
		if ($object !== null && !in_array($object, array('user', 'page', 'permissions'))) {
			throw new WV16_Exception('Invalid object type "'.$object.'" given.');
		}

		$url    = $this->getUrl();
		$params = $object === null ? array() : array('object' => $object);

		$this->api->api($url, 'DELETE', $params);
	}
}
