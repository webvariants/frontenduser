<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
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
