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
 * OpenSocial API class for Messages requests
 *
 * @author Chris Chabot
 */
class osapi_Service_Messages extends osapi_Service {

  public function get($params) {
    throw new osapi_Exception("Retrieving messages is not supported");
  }

  public function create($params) {
    if (!isset($params['message'])) throw new osapi_Exception("Missing message in osapi_Service_Messages->create()");
    if (!$params['message'] instanceof osapi_Model_Message) throw new osapi_Exception("The params['message'] should be a osapi_Model_Message in osapi_Service_Messages->create()");
    return osapi_Request::createRequest('messages.create', $params);
  }

  public function delete($params)
  {
    throw new osapi_Exception("Deleting messages is not supported");
  }

  public function update($params)
  {
    throw new osapi_Exception("Updating messages is not supported");
  }

  static public function convertarray($array, $strictMode = true) {

  }
}
