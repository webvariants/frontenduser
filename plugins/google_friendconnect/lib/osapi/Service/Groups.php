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
 * OpenSocial API class for Groups requests
 * Only the get method is supported in the OpenSocial spec.
 *
 * @author Jesse Edwards
 */
class osapi_Service_Groups extends osapi_Service {
  /**
   * Gets a list of fields supported by this service
   *
   * @return osapi_Request the request
   */
  public function getSupportedFields() {
  	 return osapi_Request::createRequest('groups.getSupportedFields', array('userId' => '@supportedFields'));
  }

  /**
   * Gets a users group.
   *
   * @param array $params the parameters defining which groups to fetch
   * @return osapi_Request the request
   */
  public function get($params) {
    return osapi_Request::createRequest('groups.get', $params);
  }

  /**
   * Update a group.
   *
   * @param array $params the parameters defining which group data to update
   * @return osapi_Request the request
   */
  public function update($params){
    throw new osapi_Exception("Updating groups is not supported");
  }

  /**
   * Deletes a group.
   *
   * @param array $params the parameters defining which group to delete
   * @return osapi_Request the request
   */
  public function delete($params){
  	throw new osapi_Exception("Deleting groups is not supported");
  }

  /**
   * Create a group
   *
   * @param array $params the parameters defining which group
   * @return osapi_Request the request
   */
  public function create($params){
  	throw new osapi_Exception("Creating groups is not supported");
  }

  /**
   * Converts a response into a native data type.
   *
   * @param array $array the raw data
   * @param boolean $strictMode whether to throw spec errors
   * @return osapi_Person
   */
  static public function convertArray($array, $strictMode = true) {
 	$instance = new osapi_Model_Group();
 	$defaults = get_class_vars('osapi_Model_Group');

 	if ($strictMode && sizeof($defaults != sizeof($array))) {
      throw new osapi_Exception("Unexpected fields in people response". print_r($array, true));
    }

  	foreach($array as $key=>$value){
  		$instance->setField($key, $value);
  	}
    return self::trimResponse($instance);
  }
}
