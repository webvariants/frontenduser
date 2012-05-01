<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_Facebook_Cache extends Facebook {
	protected $namespace;
	protected $lifetime;

	/**
	 * @param int $lifetime  lifetime of cache data in seconds
	 */
	public function __construct(array $config, $namespace, $lifetime) {
		parent::__construct($config);

		$this->namespace = $namespace;
		$this->lifetime  = $lifetime;
	}

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
		$cached    = $cache->get($namespace, $cacheKey);

		// cache hit
		if ($cached !== null) {
			$expiry = $cached['expiry'];
			$data   = $cached['data'];

			// cache data is still valid
			if ($expiry >= time()) {
				return $data;
			}
		}

		// perform actual request
		$data   = parent::makeRequest($url, $params, $ch);
		$cached = array('expiry' => time()+$this->lifetime, 'data' => $data);

		// cache result
		$cache->set($namespace, $cacheKey, $cached);

		// done
		return $data;
	}
}
