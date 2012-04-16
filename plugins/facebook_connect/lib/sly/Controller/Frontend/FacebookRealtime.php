<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_FacebookRealtime extends sly_Controller_Frontend_Base implements sly_Controller_Interface {
	public function indexAction() {
		$response = sly_Core::getResponse();
		$response->setContentType('text/plain');

		if (strtoupper($_SERVER['REQUEST_METHOD']) === 'GET') {
			$mode      = sly_get('hub_mode', 'string', '');
			$token     = sly_get('hub_verify_token', 'string', '');
			$challenge = sly_get('hub_challenge', 'string', '');

			if ($mode !== 'subscribe' || mb_strlen($token) === 0 || mb_strlen($challenge) === 0) {
				$response->setStatusCode(400);
				$response->setContent('Invalid request.');
			}
			else {
				$secret = sly_Core::config()->get('INSTNAME');

				if ($token !== $secret) {
					$response->setStatusCode(401);
					$response->setContent('Bad token.');
				}
				else {
					$response->setContent($challenge);
				}
			}
		}

		return $response;
	}
}
