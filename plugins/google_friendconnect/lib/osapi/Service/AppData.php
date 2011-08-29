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
 * OpenSocial API class for Application Data requests
 * Supported methods are get, create, update and delete
 *
 * @author Chris Chabot
 */
class osapi_Service_AppData extends osapi_Service {

  /**
   * Gets a set of appdata.
   *
   * @param array $params the parameters defining which appdata to retrieve
   * @return osapi_Request the request
   */
  public function get($params) {
    if (!isset($params['userId'])) throw new osapi_Exception("Invalid or no userId specified for osapi_Service_AppData->get");
    if (!isset($params['groupId'])) throw new osapi_Exception("Invalid or no groupId specified for osapi_Service_AppData->get");
    if (!isset($params['appId'])) throw new osapi_Exception("Invalid or no appId specified for osapi_Service_AppData->get");
      if (isset($params['fields'])) {
      if (!is_array($params['fields'])) throw new osapi_Exception("Optional param 'fields' should be an array in osapi_Service_AppData->get");
      foreach ($params['fields'] as $key) {
        if (!self::isValidKey($key)) {
          throw new osapi_Exception("Invalid key specified in osapi_Service_AppData->get: $key");
        }
      }
    }
    return osapi_Request::createRequest('appdata.get', $params);
  }

  /**
   * Creates a set of appata.
   *
   * @param array $params the parameters defining which appdata to create
   * @return osapi_Request the request
   */
  public function create($params) {
    if (!isset($params['userId'])) throw new osapi_Exception("Invalid or no userId specified for osapi_Service_AppData->create");
    if (!isset($params['groupId'])) throw new osapi_Exception("Invalid or no groupId specified for osapi_Service_AppData->create");
    if (!isset($params['appId'])) throw new osapi_Exception("Invalid or no appId specified for osapi_Service_AppData->create");
    if (!isset($params['data'])) throw new osapi_Exception("Invalid or no data array specified for osapi_Service_AppData->create");
    if (!is_array($params['data'])) throw new osapi_Exception("Invalid data specified, should be an array for osapi_Service_AppData->create");
    if (isset($params['fields']) && !is_array($params['fields'])) throw new osapi_Exception("Optional param 'fields' should be an array in osapi_Service_AppData->create");
    foreach (array_keys($params['data']) as $key) {
      if (!self::isValidKey($key)) {
        throw new osapi_Exception("Invalid key specified: $key");
      }
    }
    return osapi_Request::createRequest('appdata.create', $params);
  }

  /**
   * Updates a set of appdata.
   *
   * @param array $params the parameters defining which appdata to update
   * @return osapi_Request the request
   */
  public function update($params) {
    if (!isset($params['userId'])) throw new osapi_Exception("Invalid or no userId specified for osapi_Service_AppData->update");
    if (!isset($params['groupId'])) throw new osapi_Exception("Invalid or no groupId specified for osapi_Service_AppData->update");
    if (!isset($params['appId'])) throw new osapi_Exception("Invalid or no appId specified for osapi_Service_AppData->update");
    if (isset($params['fields']) && !is_array($params['fields'])) throw new osapi_Exception("Optional param 'fields' should be an array in osapi_Service_AppData->update");
    foreach (array_keys($params['data']) as $key) {
      if (!self::isValidKey($key)) {
        throw new osapi_Exception("Invalid key specified: $key");
      }
    }
    return osapi_Request::createRequest('appdata.update', $params);
  }

  /**
   * Deletes a set of appdata.
   *
   * @param array $params the parameters defining which appdata to delete
   * @return osapi_Request the request
   */
  public function delete($params) {
    if (!isset($params['userId'])) throw new osapi_Exception("Invalid or no userId specified for osapi_Service_AppData->delete");
    if (!isset($params['groupId'])) throw new osapi_Exception("Invalid or no groupId specified for osapi_Service_AppData->delete");
    if (!isset($params['appId'])) throw new osapi_Exception("Invalid or no appId specified for osapi_Service_AppData->delete");
    if (isset($params['fields'])) {
      if (!is_array($params['fields'])) throw new osapi_Exception("Optional param 'fields' should be an array in osapi_Service_AppData->delete");
      foreach ($params['fields'] as $key) {
        if (!self::isValidKey($key)) {
          throw new osapi_Exception("Invalid key specified in osapi_Service_AppData->delete: $key");
        }
      }
    }
    return osapi_Request::createRequest('appdata.delete', $params);
  }

  static public function convertarray($array, $strictMode = true) {
    return $array;
  }

  /**
   * Determines whether an appdata key is valid, or if it uses restricted characters.
   *
   * @param string $key the appdata key
   * @return boolean whether the key is valid
   */
  public static function isValidKey($key) {
    if (empty($key)) {
      return false;
    }
    if ($key == '*') {
      return true;
    }
    for ($i = 0; $i < strlen($key); ++ $i) {
      $c = substr($key, $i, 1);
      if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9') || ($c == '-') || ($c == '_') || ($c == '.')) {
        continue;
      }
      return false;
    }
    return true;
  }
}
