<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_Facebook_API extends Facebook {
	private static $requests = 0;

	public static function sendHeader(array $params) {
		$response = $params['subject'];
		$response->setHeader('X-Facebook-Requests', self::$requests);
	}

	/**
	 * @param  string      $url     The URL to make the request to
	 * @param  array       $params  The parameters to use for the POST body
	 * @param  CurlHandler $ch      Initialized curl handle
	 * @return string               The response text
	 */
	protected function makeRequest($url, $params, $ch = null) {
		if (sly_Core::isDeveloperMode()) {
			self::$requests++;

			$log = sly_Log::getInstance('fb-api-requests');
			$log->setFormat('[%date% %time%] [%session%] %direction% %message% [%params%]');
			$log->info($url, 2, array('params' => json_encode($params), 'session' => session_id(), 'direction' => '>>'));

			if (self::$requests === 1) {
				sly_Core::dispatcher()->register('SLY_SEND_RESPONSE', array(__CLASS__, 'sendHeader'));
			}
		}

		$response = parent::makeRequest($url, $params, $ch);

//		if (sly_Core::isDeveloperMode()) {
//			$log->info($response, 2, array('params' => '', 'session' => session_id(), 'direction' => '<<'));
//		}

		return $response;
	}
}
