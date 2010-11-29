<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/*
securepwd v1.0.2b
Class for checking the integrity of a given password.
It basis its checks in a dictionary, or in a given word (normally a user name)
By Llorenç Herrera [lha@hexoplastia.com]
*/

interface _WV16_PasswordTester {
	public function config();
	public function test($login, $password);
}
