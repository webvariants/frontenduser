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
 * OpenSocial API class for MediaItem requests
 *
 * @author Jesse Edwards
 */
class osapi_Service_MediaItems extends osapi_Service {

  /**
   * Gets a list of fields supported by this service
   *
   * @return osapi_Request the request
   */
  public function getSupportedFields() {
  	return osapi_Request::createRequest('mediaItems.getSupportedFields', array('userId' => '@supportedFields'));
  }

  /**
   * Get mediaItem(s)
   *
   * @param array $params the parameters defining the mediaItem(s) to get
   * @return osapi_Request the request
   */
  public function get($params) {
    return osapi_Request::createRequest('mediaItems.get', $params);
  }

  /**
   * Updates a mediaItem
   *
   * @param array $params the parameters defining the mediaItem data to update
   * @return osapi_Request the request
   */
  public function update($params){
    //TODO: check field restrictions
    return osapi_Request::createRequest('mediaItems.update', $params);
  }

  /**
   * Deletes a mediaItem
   *
   * @param array $params the parameters defining the mediaItem to delete
   * @return osapi_Request the request
   */
  public function delete($params){
    return osapi_Request::createRequest('mediaItems.delete', $params);
  }

  /**
   * Creates an mediaItem
   *
   * @param array $params the parameters defining the mediaItem to create
   * @return osapi_Request the request
   */
  public function create($params){
    //TODO: check field restrictions
    return osapi_Request::createRequest('mediaItems.create', $params);
  }

  /**
   * Upload mediaItem to an album
   *
   * @param array $params the parameters defining the album and mediaItem data to upload
   * @return osapi_Request the request
   */
  public function uploadContent($params){
  	return osapi_Request::createRequest('mediaItems.upload', $params);
  }

  /**
   * Converts a response into a native data type.
   *
   * @param array $array the raw data
   * @param boolean $strictMode whether to throw spec errors
   * @return osapi_Person
   */
  static public function convertarray($array, $strictMode = true) {
  	$instance = new osapi_Model_MediaItem();
 	$defaults = get_class_vars('osapi_Model_MediaItem');

 	if ($strictMode && sizeof($defaults > sizeof($array))) {
      throw new osapi_Exception("Unexpected fields in mediaItem response". print_r($array, true));
    }

  	foreach($array as $key=>$value){
  		$instance->setField($key, $value);
  	}
    return self::trimResponse($instance);
  }
}
