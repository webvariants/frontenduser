<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

interface WV16_FriendConnect_User_Interface {
	public function getFriendConnectID();
	public function getDisplayName();
	public function getName();
	public function getThumbnail();
	public function getURLs();
}