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
 * OpenSocial API class for statusmood requests
 * Only the get method is supported in the OpenSocial spec.
 *
 * @author Jesse Edwards
 */
class osapi_Service_StatusMood extends osapi_Service {
  /**
   * Gets a list of fields supported by this service
   *
   * @return osapi_Request the request
   */
  public function getSupportedFields() {
  	throw new osapi_Exception("@supportedFields for statusmood is not supported");
  }

  public function getSupportedMoods($params) {
    return osapi_Request::createRequest('statusmood.getSupportedMood', $params);
  }

  public function getHistory($params=array()) {
    $params = array_merge($params, array('userId'=>'@me', 'history'=>'history'));

    if(!array_key_exists('groupId', $params))
      $params['groupId'] = '@self';

    return osapi_Request::createRequest('statusmood.get', $params);
  }
  /**
   * Gets status and mood. Uses specific endpoint for this
   * Myspace specific
   * @return osapi_Request the request
   */
  public function get($params)
  {
      if(!array_key_exists('userId', $params))
        $params['userId'] = '@me';
      if(!array_key_exists('groupId', $params))
        $params['groupId'] = '@self';

      return osapi_Request::createRequest('statusmood.get', $params);
  }
  /**
   * Sets status. Uses specific endpoint for this
   * Myspace specific
   * @return osapi_Request the request
   */
  public function update($params)
  {
      $params = array_merge($params, array('userId'=>'@me', 'groupId'=>'@self'));
      return osapi_Request::createRequest('statusmood.update', $params);
  }

  public function delete($params)
  {
    throw new osapi_Exception("Deleting statusmood is not supported");
  }

  public function create($params)
  {
    throw new osapi_Exception("Creating statusmood is not supported");
  }

  /**
   * Converts a response into a native data type.
   *
   * @param array $array the raw data
   * @param boolean $strictMode whether to throw spec errors
   * @return osapi_Person
   */
  static public function convertArray($array, $strictMode = true) {
  	$instance = new osapi_Model_StatusMood();
 	$defaults = get_class_vars('osapi_Model_StatusMood');

 	if ($strictMode && sizeof($defaults != sizeof($array))) {
      throw new osapi_Exception("Unexpected fields in statusmood response". print_r($array, true));
    }
  	foreach($array as $key=>$value){
  		$instance->setField($key, $value);
  	}
    return self::trimResponse($instance);
  }
}