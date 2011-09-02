<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

interface WV16_FacebookConnect_User_Interface {
	public function getFacebookID();
	public function getName();
	public function getFirstname();
	public function getLastname();
	public function getLink();
	public function getUsername();
	public function getEMail();
	public function getGender();
	public function getTimezone();
	public function getLocale();
	public function isVerified();
	public function isRegistered();
}
