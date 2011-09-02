<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FacebookConnect_API extends Facebook {
	/**
	 * @param  string      $url     The URL to make the request to
	 * @param  array       $params  The parameters to use for the POST body
	 * @param  CurlHandler $ch      Initialized curl handle
	 * @return string               The response text
	 */
	protected function makeRequest($url, $params, $ch = null) {
		$cache     = sly_Core::cache();
		$namespace = $this->getNamespace();
		$cacheKey  = substr(md5($url.json_encode($params)), 0, 12);
		$result    = $cache->get($namespace, $cacheKey);

		if ($result === null) {
			$result = parent::makeRequest($url, $params, $ch);
			$cache->set($namespace, $cacheKey, $result);
		}

		return $result;
	}

	protected function getNamespace() {
		return 'fc.users.'.$this->getUser();
	}
}
