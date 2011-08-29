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
 * Class for local debugging and development, inherits the osapi_Provider_Partuza's
 * preRequestProcess but overwrites the shindig and partuza URLs.
 * @param string $httpProvider The HTTP provider to use.
 * @param string $partuzaUrl The base location of a Partuza instance.  Defaults
 *     to "http://partuza".
 * @param string $shindigUrl The base location of a Shindig instance.  Defaults
 *     to "http://shindig".
 */
class osapi_Provider_LocalPartuza extends osapi_Provider_Partuza {
  public function __construct(osapi_IO_Provider_Http $httpProvider = null, $partuzaUrl = "http://partuza", $shindigUrl = "http://shindig") {
    parent::__construct($httpProvider);
    $partuzaUrl = $this->trimSlash($partuzaUrl);
    $shindigUrl = $this->trimSlash($shindigUrl);
    $this->requestTokenUrl = $partuzaUrl . "/oauth/request_token";
    $this->authorizeUrl = $partuzaUrl . "/oauth/authorize";
    $this->accessTokenUrl = $partuzaUrl . "/oauth/access_token";
    $this->restEndpoint = $shindigUrl . "/social/rest";
    $this->rpcEndpoint = $shindigUrl . "/social/rpc";
    $this->providerName = "LocalPartuza";
    $this->isOpenSocial = true;
  }

  /**
   * Given an url, this function returns the same url with a trailing slash
   * removed, if it exists.
   */
  private function trimSlash($url) {
    if (substr($url, -1) == "/") {
      return substr($url, 0, -1);
    }
    return $url;
  }
}
