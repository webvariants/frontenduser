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
	}

	sly_Core::cache()->flush('frontenduser', true);
}
catch (Exception $e) {
	die('Error while updating FrontendUser: '.$e->getMessage());
}
