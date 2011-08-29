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
 * Pre-defined provider class for Google
 * @author Arne Roomann-Kurrik
 */
class osapi_Provider_Google extends osapi_Provider {

  /**
   * Specifies the appropriate data for an orkut request.
   * @param osapi_IO_Provider_Http httpProvider The HTTP request provider to use.
   */
  public function __construct(osapi_IO_Provider_Http $httpProvider = null) {
    parent::__construct('https://www.google.com/accounts/OAuthGetRequestToken', 'https://www.google.com/accounts/OAuthAuthorizeToken', 'https://www.google.com/accounts/OAuthGetAccessToken', 'http://www-opensocial.googleusercontent.com/api/', 'http://www-opensocial.googleusercontent.com/api/rpc', "Google", true, $httpProvider);
  }

  /**
   * Parameters to include in the OAuth request token call (some containers
   * need a scope parameter).
   * @var array
   */
  public $oauthRequestTokenParams = array('scope' => 'http://www-opensocial.googleusercontent.com/api/ http://www-opensocial.googleusercontent.com/api/rpc');

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
