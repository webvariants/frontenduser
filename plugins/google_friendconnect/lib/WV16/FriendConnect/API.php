<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FriendConnect_API {
	protected $site;
	protected $auth;

	const PEOPLE_REST   = 'https://www.google.com/friendconnect/api/people/';
	const ACTIVITY_REST = 'https://www.google.com/friendconnect/api/activities/';
	const APPDATA_REST  = 'https://www.google.com/friendconnect/api/appdata/';

	public function __construct($site, $auth) {
		$this->site = $site;
		$this->auth = $auth;
	}

	protected function apiCall($endpoint, $request) {
		var_dump(file_get_contents($endpoint.$request.'?fcauth='.$this->auth));
	}

	public function getUser() {
		$this->apiCall(self::PEOPLE_REST, '@me/@self');
	}
}
