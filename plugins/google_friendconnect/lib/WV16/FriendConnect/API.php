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
	protected $authToken;
	protected $osapi;
	protected $provider;
	protected $auth;

	public function __construct($authToken) {
		$this->authToken = $authToken;
		$this->provider  = new osapi_Provider_FriendConnect();
		$this->auth      = new osapi_Auth_FCAuth($authToken);
		$this->osapi     = new osapi($this->provider, $this->auth);
	}

	public function getMe() {
		// since this is needed to get the correct namespace ID, we cannot use the
		// regular caching methods for doing this request. *But* we can manually
		// cache the result ourselves.

		$cache     = sly_Core::cache();
		$namespace = 'gfc.tokens';
		$cacheKey  = md5(json_encode($this->auth));
		$me        = $cache->get($namespace, $cacheKey);

		if ($me === null) {
			$request = $this->osapi->people->get(array('userId' => '@me', 'groupId' => '@self', 'fields' => '@all'));
			$batch   = $this->osapi->newBatch();

			$batch->add($request, 'me');

			$result = $batch->execute();
			$me     = $result['me'];

			$cache->set($namespace, $cacheKey, $me);
		}

		return $me;
	}

	/**
	 * @return WV16_FriendConnect_Batch
	 */
	public function getBatch() {
		return new WV16_FriendConnect_Batch($this->provider, $this->auth, $this->osapi->getStrictMode());
	}

	public function executeRequest(osapi_Request $request) {
		$key   = 'a'.uniqid();
		$batch = $this->getBatch();

		$batch->add($request, $key);
		$result = $this->executeBatch($batch);

		return $result[$key];
	}

	public function executeBatch(WV16_FriendConnect_Batch $batch) {
		$requests = $batch->getRequests();
		$caches   = array();

		// read already known values from cache

		foreach ($requests as $key => $request) {
			$cacheData = $this->getCacheData($request);

			if ($cacheData !== null) {
				$batch->removeRequest($key);
				$caches[$key] = $cacheData;
			}
		}

		// execute the remaining requests

		$result = $batch->execute();

		// cache the results

		foreach ($result as $key => $value) {
			$request = $batch->getRequest($key);
			$this->setCacheData($request, $value);
		}

		// put cached data in $result

		foreach ($caches as $key => $data) {
			$result[$key] = $data;
		}

		return $result;
	}

	protected function getNamespace() {
		return 'gfc.users.'.$this->getFriendConnectID();
	}

	protected function getCacheData(osapi_Request $request) {
		$cache     = sly_Core::cache();
		$namespace = $this->getNamespace();
		$cacheKey  = $cache->generateKey($request);

		return $cache->get($namespace, $cacheKey);
	}

	protected function setCacheData(osapi_Request $request, $data) {
		$cache     = sly_Core::cache();
		$namespace = $this->getNamespace();
		$cacheKey  = $cache->generateKey($request);

		$cache->set($namespace, $cacheKey, $data);
	}

	public function getFriendConnectID() {
		return $this->getMe()->id;
	}
}
