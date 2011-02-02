<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser_Exports extends sly_Controller_Frontenduser {
	protected function index() {
		$exports = $this->getExports();
		$this->render('addons/frontenduser/templates/exports.phtml', compact('exports'));
	}

	private function getExports() {
		$filename = SLY_DEVELOPFOLDER.'/frontenduser-exports.yml';
		if (!file_exists($filename)) throw new sly_Exception($filename.' konnte nicht gefunden werden.');
		return sly_Util_YAML::load($filename);
	}

	protected function export() {
		$exports = $this->getExports();
		$export  = sly_get('export', 'string');

		// check export

		if (!isset($exports[$export])) {
			print rex_warning('Der angeforderte Export konnte nicht gefunden werden.');
			return $this->index();
		}

		$key    = $export;
		$export = $exports[$export];

		// find users

		$type  = _WV16_UserType::getInstance($export['usertype'])->getID();
		$sql   = WV_SQLEx::getInstance();
		$users = $sql->getArray('SELECT id FROM ~wv16_users WHERE type_id = ?', $type, '~');

		if (empty($users)) {
			print rex_warning('Es wurden keine passenden Benutzer gefunden.');
			return $this->index();
		}

		// prepare head

		WV_Redaxo::clearOutput();
		ob_start('ob_gzhandler');

		$nl      = "\n";
		$headers = array('\'ID', 'Login'); // ' vor dem ID, sonst denkt Excel, dass es sich um eine SYLK-Datei handelt (http://support.microsoft.com/kb/215591/de)

		// write header line

		foreach ($export['attributes'] as $attrName) {
			$attribute = _WV16_Attribute::getInstance($attrName);
			$headers[] = str_replace(array('"', "'", ';'), '', $attribute->getTitle());
		}

		print implode(';', $headers).$nl;

		// write user data

		foreach ($users as $userID) {
			$user = _WV16_User::getInstance($userID);
			$line = array($userID, $user->getLogin());

			foreach ($export['attributes'] as $attrName) {
				$value = $user->getValue($attrName)->getValue();

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
}