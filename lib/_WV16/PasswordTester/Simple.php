<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_PasswordTester_Simple implements _WV16_PasswordTester {
	public function config() {
	}

	public function test($login, $password) {
		if (strlen($password) < 6) {
			throw new WV_InputException('Das Passwort ist zu kurz (mindestens 6 Zeichen!)');
		}

		if ($password == $login || $password == strrev($login)) {
			throw new WV_InputException('Das Passwort darf den Loginnamen nicht enthalten!');
		}

		return true;
	}
}
