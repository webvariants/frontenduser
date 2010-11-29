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

class _WV16_PasswordTester_Simple implements _WV16_PasswordTester {
	public function config() {
	}

	public function test($login, $password) {
		if (strlen($password) < 6) {
			throw new WV_InputException('Das Passwort ist zu kurz (mindestens 6 Zeichen!)', self::ERR_PWD_TOO_SHORT);
		}

		if ($password == $login || $password == strrev($login)) {
			throw new WV_InputException('Das Passwort darf den Loginnamen nicht enthalten!');
		}

		return true;
	}
}
