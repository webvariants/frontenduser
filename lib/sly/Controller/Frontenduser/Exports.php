<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser_Exports extends sly_Controller_Frontenduser {
	protected function index() {
		$exports = $this->getExports();
		print $this->render('exports.phtml', compact('exports'));
	}

	private function getExports() {
		return sly_Core::config()->get('frontenduser/exports');
	}

	protected function export() {
		$exports = $this->getExports();
		$export  = sly_get('export', 'string');

		// check export

		if (!isset($exports[$export])) {
			print sly_Helper_Message::warn('Der angeforderte Export konnte nicht gefunden werden.');
			return $this->index();
		}

		$key    = $export;
		$export = $exports[$export];

		// find users

		$type  = $export['usertype'];
		$sql   = WV_SQL::getInstance();
		$users = $sql->getArray('SELECT id FROM ~wv16_users WHERE `type` = ?', $type, '~');

		if (empty($users)) {
			print sly_Helper_Message::warn('Es wurden keine passenden Benutzer gefunden.');
			return $this->index();
		}

		// prepare head

		while (ob_get_level()) ob_end_clean();
		ob_start('ob_gzhandler');

		$nl      = "\n";
		$headers = array('\'ID', 'Login'); // ' vor dem ID, sonst denkt Excel, dass es sich um eine SYLK-Datei handelt (http://support.microsoft.com/kb/215591/de)

		// write header line

		foreach ($export['attributes'] as $attrName) {
			$attribute = WV16_Factory::getAttribute($attrName);
			$headers[] = str_replace(array('"', "'", ';'), '', $attribute->getTitle());
		}

		print "\xef\xbb\xbf"; // UTF8-BOM
		print implode(';', $headers).$nl;

		// write user data

		foreach ($users as $userID) {
			$user = _WV16_User::getInstance($userID);
			$line = array($userID, $user->getLogin());

			foreach ($export['attributes'] as $attrName) {
				$value = $user->getValue($attrName);

				if (is_array($value)) {
					$value = implode(', ', $value);
				}
				elseif (is_bool($value) || $value === '0' || $value === '1') {
					$value = $value ? 'ja' : 'nein';
				}

				$line[] = str_replace(array('"', "'", ';'), '', $value);
			}

			print implode(';', $line).$nl;
		}

		// send file

		$filename = $key.'_'.date('Ymd').'.csv';
		header('Content-Type: application/csv; charset="UTF-8"');
		header('Content-Disposition: attachment; filename='.$filename);
		ob_end_flush();
		die;
	}

	protected function checkPermission() {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('frontenduser[exports]'));
	}
}
