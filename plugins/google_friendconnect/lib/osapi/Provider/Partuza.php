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

/**
 * Pre-defined provider class for Partuza (partuza)
 * @author Chris Chabot
 */
class osapi_Provider_Partuza extends osapi_Provider {
  public function __construct(osapi_IO_Provider_Http $httpProvider = null) {
    parent::__construct("http://www.partuza.nl/oauth/request_token", "http://www.partuza.nl/oauth/authorize", "http://www.partuza.nl/oauth/access_token", "http://modules.partuza.nl/social/rest", "http://modules.partuza.nl/social/rpc", "Partuza", true, $httpProvider);
  }

  /**
   * Set's the signer's useBodyHash to true
   * @param mixed $request The osapi_Request object being processed, or an array
   *     of osapi_Request objects.
   * @param string $method The HTTP method used for this request.
   * @param string $url The url being fetched for this request.
   * @param array $headers The headers being sent in this request.
   * @param osapi_Auth $signer The signing mechanism used for this request.
   */
  public function preRequestProcess(&$request, &$method, &$url, &$headers, osapi_Auth &$signer) {
    if (method_exists($signer, 'setUseBodyHash')) {
      $signer->setUseBodyHash(true);
    }
  }
}
