<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace wv\FrontendUser;

interface UserInterface {
	public function getLogin();
	public function getID();
	public function getValue($attribute, $default = null);
	public function setValue($attribute, $value);
}
