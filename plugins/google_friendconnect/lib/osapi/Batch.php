<?php
/*
 * Copyright 2008 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class osapi_Batch {
  private $strictMode;
  private $provider;
  private $signer;
  private $requests = array();

  public function __construct($provider, $signer, $strictMode) {
    $this->provider = $provider;
    $this->signer = $signer;
    $this->strictMode = $strictMode;
  }

  /**
   * Adds a osapi_Request to the batch queue
   *
   * @param osapi_Request $request
   * @param string $key identifyer used in the response object
   */
  public function add(osapi_Request $request, $key = null) {
    if (isset($this->requests[$key])) {
      throw new osapi_Exception("Duplicate key in osapi_Batch");
    }
    if (! empty($key)) {
      $request->id = $key;
    }
    $this->requests[] = $request;
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
    if (!count($this->requests)) {
      throw new osapi_Exception("Can't execute batch, no requests specified");
    }
    if (! empty($this->provider->rpcEndpoint)) {
      // Send batch through the RPC interface if it is available
      return osapi_IO_Rpc::sendBatch($this->requests, $this->provider, $this->signer, $this->strictMode);
    } elseif (! empty($this->provider->restEndpoint)) {
      // Otherwise use the REST endpoint if available (this is slower since it has to do a round trip per request)
      return osapi_IO_Rest::sendBatch($this->requests, $this->provider, $this->signer, $this->strictMode);
    } else {
      // No usable endpoints defined, woops!
      throw new osapi_Exception("No valid RPC or REST endpoints found");
    }
  }
}
