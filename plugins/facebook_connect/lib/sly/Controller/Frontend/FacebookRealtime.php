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
		$method   = strtoupper($_SERVER['REQUEST_METHOD']);
		$response = sly_Core::getResponse();
		$response->setContentType('text/plain');

		if ($method === 'GET') {
			$response = $this->handleVerification($response);
		}
		elseif ($method === 'POST') {
			$response = $this->handleNotification($response);
		}
		else {
			$response->setStatusCode(400);
			$response->setContent('Invalid request.');
		}

		return $response;
	}

	private function handleVerification(sly_Response $response) {
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

		return $response;
	}

	private function handleNotification(sly_Response $response) {
		$raw = file_get_contents('php://input');

		// collect some debugging data
		$log = sly_Log::getInstance('fbrt');
		$log->dump('raw', $raw);

		$log = sly_Log::getInstance('fbrt_debug');

		if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
			$response->setStatusCode(400);
			$response->setContent('Invalid request.');
			$log->warning('No signature received.');
		}
		elseif (!function_exists('hash_hmac')) {
			$log->warning('Cannot use hash_hmac().');
		}
		else {
			$sig   = $_SERVER['HTTP_X_HUB_SIGNATURE'];
			$parts = explode('=', $sig);

			if (count($parts) !== 2) {
				$response->setStatusCode(400);
				$response->setContent('Bad signature format.');
			}
			elseif (!in_array($parts[0], hash_algos())) {
				$response->setStatusCode(400);
				$response->setContent('Bad signature hash algorithm.');
			}
			else {
				// set the current language so globalsettings can retrieve the app secret later on
				sly_Core::setCurrentClang(sly_Core::getDefaultClangId());

				$secret = '5fba9c64b4b685f5fc7902fbdffeb8c6'; // WV16_FacebookConnect::getAppSecret();
				$algo   = $parts[0];
				$hash   = $parts[1];
				$check  = hash_hmac($algo, $raw, $secret);

				if ($hash !== $check) {
					$response->setStatusCode(400);
					$response->setContent('Invalid request.');

					$log->warning('Bad signature: got '.$hash.' but computed '.$check.'.');
				}
				else {
					$log->info('Signature was valid.');
				}
			}
		}

		return $response;
	}
}
