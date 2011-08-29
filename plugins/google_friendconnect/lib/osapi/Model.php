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
 * Meta file that includes all the OpenSocial model definitions.
 * The model files them selves have been adopted from the php
 * version of shindig: http://incubator.apache.org/shindig and
 * are also licenced under the Apache License, version 2.0
 *
 * @author Chris Chabot
 * @author Jesse Edwards
 */
class osapi_Model
{
  /**
   * Standardized method for getting fields from osapi_Models
   * @param string $field
   * @return mixed
   */
  public function getField($field)
  {
    return !!$this->{$field} ? $this->{$field} : null;
  }

  /**
   * Standardized method for setting fields for osapi_Models
   * @param string $field
   * @param mixed $value
   * @return none
   */
  public function setField($field, $value)
  {
  	$this->{$field} = $value;
  }
}

