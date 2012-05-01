<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_FriendConnect_Batch {
	private $strictMode;
	private $provider;
	private $signer;
	private $requests = array();

	public function __construct($provider, $signer, $strictMode) {
		$this->provider   = $provider;
		$this->signer     = $signer;
		$this->strictMode = $strictMode;
	}

	/**
	 * Adds a osapi_Request to the batch queue
	 *
	 * @param osapi_Request $request
	 * @param string        $key      identifier used in the response object
	 */
	public function add(osapi_Request $request, $key) {
		if (isset($this->requests[$key])) {
			throw new osapi_Exception('Duplicate key in WV16_FriendConnect_Batch');
		}

		$request->id = $key;
		$this->requests[$key] = $request;
	}

	/**
	 * @return array
	 */
	public function getRequests() {
		return $this->requests;
	}

	/**
	 * @return osapi_Request
	 */
	public function getRequest($key) {
		return isset($this->requests[$key]) ? $this->requests[$key] : null;
	}

	/**
	 * @param string $key
	 */
	public function removeRequest($key) {
		unset($this->requests[$key]);
	}

	public function clear() {
		$this->requests = array();
	}

	/**
	* Executes the batched request(s) and returns an array
	* with the $key => $result results.
	*
	* If an wire error occurs, this function will throw an osapi_Exception
	*
	* On API errors each individual result will contain it's own error code
	*/
	public function execute() {
		if (empty($this->requests)) return array();

		$batch = new osapi_Batch($this->provider, $this->signer, $this->strictMode);

		foreach ($this->requests as $request) {
			$batch->add($request);
		}

		return $batch->execute();
	}
}
