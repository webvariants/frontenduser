<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

$service    = sly_Service_Factory::getAddOnService();
$oldVersion = $service->getKnownVersion('frontenduser');
$newVersion = $service->getVersion('frontenduser');

// 1.1: confirmation_code
// 1.2: helptext + hidden
// 2.0: activated + confirmed, internal entfernt, wv16_rights entfernt

function _wv16_addColumnSetting() {
	if (WV8_Settings::exists('frontenduser', 'be_columns')) return;

	$prefix = sly_Core::config()->get('DATABASE/TABLE_PREFIX');

	WV8_Settings::create(
		/*     Namespace */ 'frontenduser',
		/*          Name */ 'be_columns',
		/*         Titel */ 'Spalten in Benutzerliste',
		/*     Hilfetext */ 'Wählen Sie, welche Informationen im Backend zusätzlich zum Login angezeigt werden sollen.',
		/*      Datentyp */ 3,
		/*     Parameter */ '1|1_1_0_5|SELECT name, title FROM '.$prefix.'wv16_attributes WHERE 1 ORDER BY title',
		/*        lokal? */ false,
		/*    Seitenname */ 'translate:frontenduser_title',
		/*        Gruppe */ 'Backend',
		/* mehrsprachig? */ false
	);
}

try {
	$sql = WV_SQLEx::getInstance();

	if (version_compare($oldVersion, '1.1', '<')) {
		$sql->queryEx('ALTER TABLE ~wv16_users ADD COLUMN `confirmation_code` varchar(20) NOT NULL DEFAULT "" AFTER `was_activated`', null, '~');
	}

	if (version_compare($oldVersion, '1.2', '<')) {
		// helptext ergänzen
		$sql->queryEx('ALTER TABLE ~wv16_attributes ADD COLUMN `helptext` varchar(1024) NOT NULL DEFAULT "" AFTER `title`', null, '~');
		$sql->queryEx('ALTER TABLE ~wv16_attributes ADD COLUMN `hidden` tinyint(1) unsigned not null default "0" AFTER `default_value`', null, '~');
	}

	if (version_compare($oldVersion, '2.0', '<')) {
		// Spalten ergänzen
		$sql->queryEx('ALTER TABLE ~wv16_users ADD COLUMN `activated` tinyint(1) unsigned NOT NULL DEFAULT "0" AFTER `deleted`', null, '~');
		$sql->queryEx('ALTER TABLE ~wv16_users ADD COLUMN `confirmed` tinyint(1) unsigned NOT NULL DEFAULT "0" AFTER `activated`', null, '~');

		// Alle Benutzer durchgehen und die Gruppen in Stati umwandeln

		$data = $sql->getArray(
			'SELECT id, '.
				'IF(ISNULL(ug2.group_id), 0, 1) AS confirmed, '.
				'IF(ISNULL(ug3.group_id), 0, 1) AS activated '.
			'FROM ~wv16_users u '.
				'LEFT JOIN ~wv16_user_groups ug2 ON u.id = ug2.user_id AND ug2.group_id = 2 '.
				'LEFT JOIN ~wv16_user_groups ug3 ON u.id = ug3.user_id AND ug3.group_id = 3', null, '~');

		$statement = $sql->prepareEx('UPDATE ~wv16_users SET activated = ?, confirmed = ? WHERE id = ?', '~');

		foreach ($data as $userID => $info) {
			$sql->queryEx($statement, array($info['activated'], $info['confirmed'], $userID));
		}

		// Gruppenzugehörigkeiten entfernen
		$sql->queryEx('DELETE FROM ~wv16_user_groups WHERE id IN (1,2,3)', null, '~');
		$sql->queryEx('ALTER TABLE ~wv16_groups DROP COLUMN `internal`', null, '~');

		// wv16_rights entfernen
		$sql->queryEx('DROP TABLE IF EXISTS ~wv16_rights', null, '~');

		// Global Setting nachtragen (wenn alle AddOns geladen sind)
		sly_Core::dispatcher()->register('ADDONS_INCLUDED', '_wv16_addColumnSetting');
	}

	sly_Core::cache()->flush('frontenduser', true);
}
catch (Exception $e) {
	die('Error while updating FrontendUser: '.$e->getMessage());
}
