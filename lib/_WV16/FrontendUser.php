<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class _WV16_FrontendUser {
	const DEFAULT_USER_TYPE = 'default';

	public static function getIDForUser($user, $allowNull = true) {
		if ($user === null && $allowNull) return null;
		if ($user instanceof _WV16_User) return $user->getID();
		return (int) $user;
	}

	/**
	 * POST-Daten ausgeben
	 *
	 * Diese Methode erzeugt versteckte Formular-Elemente fÃ¼r alle Daten im
	 * superglobalen Array $_POST. Damit wird das erneute Versenden eines
	 * Formulars möglich.
	 */
	public static function printPOSTData() {
		foreach ($_POST as $key => $value) {
			if (!is_array($value)) {
				print '<input type="hidden" name="'.sly_html($key).'" value="'.sly_html($value).'" />';
			}
			else {
				foreach ($value as $v) {
					print '<input type="hidden" name="'.sly_html($key).'[]" value="'.sly_html($v).'" />';
				}
			}
		}
	}
}
