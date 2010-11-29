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

class _WV16_PasswordTester_Secure implements _WV16_PasswordTester {
	public function config() {
	}

	public function test($login, $password) {
		$password = strtolower($password);

		if (strlen($password) < 6) {
			throw new WV_InputException('Das Passwort ist zu kurz (mindestens 6 Zeichen!)');
		}

		// Falls der Benutzername enthalten ist...

		if (stristr($password, $login)) {
			throw new WV_InputException('Das Passwort darf den Loginnamen nicht enthalten!');
		}

		// Besteht das Passwort nur aus Zahlen?

		if (preg_match('#^[0-9]$#', $password)) {
			throw new WV_InputException('Das Passwort ist anfällig gegenüber Wörterbuch-Angriffen!');
		}

		// If similarity between baseword and password is less than the specified percentage, based on Oliver algorythm

		$percentage   = 0;
		$chardistance = similar_text($password, $login, &$percentage);

		if ($percentage >= 20 || (strlen($password) - $chardistance) < 2) {
			throw new WV_InputException('Das Passwort ist dem Benutzernamen zu ähnlich!');
		}

		// Haben beide Daten die gleiche Aussprache?

		if (soundex($password) == soundex($login)) {
			throw new WV_InputException('Das Passwort ist dem Benutzernamen zu ähnlich!');
		}

		// Haben beide den gleichen Metaphone-Schlüssel?

		if (metaphone ($password) == metaphone ($login)) {
			throw new WV_InputException('Das Passwort ist dem Benutzernamen zu ähnlich!');
		}

		// Wörterbuch-Angriff

		$dictionary = _WV16_PATH.'pwddata/passwords.txt';
		$soundexPwd = soundex($password);

		if (file_exists($dictionary)) {
			$fp = @fopen($dictionary, 'r');

			if ($fp) {
				while ($line = fgets($fp, 128)) {
					$line = trim($line);

					if ($password == $line || $soundexPwd == soundex($line)) {
						fclose($fp);
						throw new WV_InputException('Das Passwort ist anfällig gegenüber Wörterbuch-Angriffen!');
					}
				}

				fclose($fp);
			}
		}

		return true;
	}
}
