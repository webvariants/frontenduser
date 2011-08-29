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
 * The osapi_Provider class is used to define your OAuth and OpenSocial API endpoints
 * osapi also works with PortableContacts end points, in which case
 * $osapi->isOpenSocial() will return false, and any attempt to use
 * AppData, Activities and Messages will trigger an exception since
 * PortableContacts only supports the people end-point.
 *
 * Either use one of the predefined ones by doing:
 *   new osapi(new osapiMySpaceProvider());
 *
 * or use XRDS discovery:
 *   new osapi(new osapiXrdsProvider('http://partuza'));
 *
 * or by manually defining the end-points:
 *   new osapi(new osapiProvider($requestTokenUrl, $authorizeUrl, $accessTokenUrl, $restEndpoint,
 *              $rpcEndpoint, $providerName, $isOpenSocial));
 *
 * The pre-defined providers are:
 *   osapiPartuzaProvider
 *   osapiGoogleProvider
 *   osapiMySpaceProvider
 *   osapiPlaxoProvider
 *   osapiOrkutProvider (note: currently orkut only supports 2-legged OAuth and doesn't support activities)
 *
 * @author Chris Chabot
 */
class osapi_Provider {
  public $requestTokenUrl;
  public $authorizeUrl;
  public $accessTokenUrl;
  public $restEndpoint;
  public $rpcEndpoint;
  public $providerName;
  public $isOpenSocial;
  public $httpProvider;
  public $requestTokenParams = array();

  public function __construct($requestTokenUrl, $authorizeUrl, $accessTokenUrl, $restEndpoint, $rpcEndpoint, $providerName, $isOpenSocial, $httpProvider = null) {
    $this->requestTokenUrl = $requestTokenUrl;
    $this->authorizeUrl = $authorizeUrl;
    $this->accessTokenUrl = $accessTokenUrl;
    $this->restEndpoint = $restEndpoint;
    $this->rpcEndpoint = $rpcEndpoint;
    $this->providerName = $providerName;
    $this->isOpenSocial = $isOpenSocial;
    if ($httpProvider) {
      $this->httpProvider = $httpProvider;
    } else {
      $this->httpProvider = new osapi_IO_Provider_Curl();
    }
  }
}
