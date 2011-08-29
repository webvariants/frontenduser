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
 * OpenSocial API class for Albums requests
 *
 * @author Jesse Edwards
 */
class osapi_Service_Albums extends osapi_Service {

  /**
   * Gets a list of fields supported by this service
   *
   * @return osapi_Request the request
   */
  public function getSupportedFields() {
  	return osapi_Request::createRequest('albums.getSupportedFields', array('userId' => '@supportedFields'));
  }

  /**
   * Gets albums
   *
   * @param array $params the parameters defining which albums to retrieve
   * @return osapi_Request the request
   */
  public function get($params) {
    return osapi_Request::createRequest('albums.get', $params);
  }

  /**
   * Updates an album
   *
   * @param array $params the parameters defining the album data to update
   * @return osapi_Request the request
   */
  public function update($params){
    if (!isset($params['album'])) throw new osapi_Exception("Missing album in osapi_Service_Albums->update()");
    if (!$params['album'] instanceof osapi_Model_Album) throw new osapi_Exception("The params['album'] should be a osapi_Model_Album in osapi_Service_Albums->update()");
    //TODO: check album.field restrictions
    return osapi_Request::createRequest('albums.update', $params);
  }

  /**
   * Deletes an album
   *
   * @param array $params the parameters defining the album to delete
   * @return osapi_Request the request
   */
  public function delete($params){
  	throw new osapi_Exception("Deleting albums is not supported");
  }

  /**
   * Creates an album
   *
   * @param array $params the parameters defining the album to create
   * @return osapi_Request the request
   */
  public function create($params){
  	if (!isset($params['album'])) throw new osapi_Exception("Missing album in osapi_Service_Albums->create()");
    if (!$params['album'] instanceof osapi_Model_Album) throw new osapi_Exception("The params['album'] should be a osapi_Model_Album in osapi_Service_Albums->create()");
    //TODO: check album.field restrictions
    return osapi_Request::createRequest('albums.create', $params);
  }

  /**
   * Converts a response into a native data type.
   *
   * @param array $array the raw data
   * @param boolean $strictMode whether to throw spec errors
   * @return osapi_Person
   */
  static public function convertArray($array, $strictMode = true) {
 	$instance = new osapi_Model_Album();
 	$defaults = get_class_vars('osapi_Model_Album');

 	if ($strictMode && sizeof($defaults != sizeof($array))) {
      throw new osapi_Exception("Unexpected fields in people response". print_r($array, true));
    }

  	foreach($array as $key=>$value){
  		$instance->setField($key, $value);
  	}
    return self::trimResponse($instance);
  }
}
