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
 * Base IO class, the REST and RPC implementations inherit from this class.
 *
 * @author Chris Chabot
 */
abstract class osapi_IO {
  const USER_AGENT = 'osapi 1.0';

  protected static function convertArray(osapi_Request $request, $val, $strictMode) {
    $converted = null;
    $service = $request->getService($request->method);
    $method = substr($request->method,stripos($request->method,'.')+1);

    // don't converArray on responses that do not need to be placed into
    // their respective models. (supportedFields, delete, create, update)
    if($method == 'get'){
        switch ($service) {
          case 'people':
            $converted = osapi_Service_People::convertArray($val, $strictMode);
            break;
          case 'activities':
            $converted = osapi_Service_Activities::convertArray($val, $strictMode);
            break;
          case 'appdata':
            $converted = osapi_Service_AppData::convertArray($val, $strictMode);
            break;
          case 'messages':
            $converted = osapi_Service_Messages::convertArray($val, $strictMode);
            break;
          case 'mediaItems':
            $converted = osapi_Service_MediaItems::convertArray($val, $strictMode);
            break;
          case 'albums':
            $converted = osapi_Service_Albums::convertArray($val, $strictMode);
            break;
          case 'statusmood':
            $converted = osapi_Service_StatusMood::convertArray($val, $strictMode);
            break;
          case 'notifications':
            $converted = osapi_Service_Notifications::convertArray($val, $strictMode);
            break;
          case 'groups':
            $converted = osapi_Service_Groups::convertArray($val, $strictMode);
            break;
        }
    }
    return $converted ? $converted : $val;
  }

  /**
   * Converts a collection response array into a collection object.
   *
   * @param array $entry
   * @return osapi_Collection
   */
  protected static function listToCollection($entry, $strictMode) {
    // Result is a data collection, return as a osapi_Collection
    $offset = isset($entry['startIndex']) ? $entry['startIndex'] : 0;
    $totalSize = isset($entry['totalResults']) ? $entry['totalResults'] : 0;
    $collection = new osapi_Collection($entry['list'], $offset, $totalSize);
    if (isset($entry['itemsPerPage'])) {
      $collection->setItemsPerPage($entry['itemsPerPage']);
    }
    if (isset($entry['sorted'])) {
      $sorted = $entry['sorted'];
      $sorted = ($sorted == 1 || $sorted == 'true' || $sorted == true) ? true : false;
      $collection->setSorted($sorted);
    }
    if (isset($entry['filtered'])) {
      $filtered = $entry['filtered'];
      $filtered = ($filtered == 1 || $filtered == 'true' || $filtered == true) ? true : false;
      $collection->setFiltered($filtered);
    }
    if (isset($entry['updatedSince'])) {
      $updatedSince = $entry['updatedSince'];
      $updatedSince = ($updatedSince == 1 || $updatedSince == 'true' || $updatedSince == true) ? true : false;
      $collection->setUpdatedSince($updatedSince);
    }
    return $collection;
  }

  /**
   * This function sends the request batch, implemented in the sub-classes
   * (RPC and REST IO classes), for some reason PHP doesn't allow
   * abstract public static functions, hence the empty declaration.
   *
   * @param array $requests
   * @param osapi_Provider $provider
   * @param osapi_Auth $signer
   */
  public static function sendBatch(Array $requests, osapi_Provider $provider, osapi_Auth $signer, $strictMode = false) {
    throw new osapi_Exception("osapi_IO Should not be used directly, use osapi_IO_Rpc or osapi_IO_Rest instead");
  }

  /**
   * Function that performs the nitty-gritty work to send the request.
   *
   * @param string $url URL to request
   * @param string $method method to use (GET, POST, PUT, DELETE)
   * @param osapi_IO_Provider_Http the HTTP provider to use (such as local or curl)
   * @param array $headers optional: Headers to include in the request
   * @param string $postBody optional: postBody to post
   * @return array('http_code' => HTTP response code (200, 404, 401, etc), 'data' => the html document, 'headers' => parsed response headers)
   */
  public static function send($url, $method, $httpProvider, $headers = false, $postBody = false) {
    return $httpProvider->send($url, $method, $postBody, $headers);
  }
}
