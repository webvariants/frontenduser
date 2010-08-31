<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

if (defined('IS_SALLY')) {
	$service         = sly_Service_Factory::getService('Addon');
	$devUtilsPresent = $service->isAvailable('developer_utils');
	$devUtilsVersion = $service->getVersion('developer_utils');
	$gsPresent       = $service->isAvailable('global_settings');
	$gsVersion       = $service->getVersion('global_settings');
}
else {
	$devUtilsPresent = rex_addon::isAvailable('developer_utils');
	$devUtilsVersion = $devUtilsPresent ? $REX['ADDON']['version']['developer_utils'] : '0.0';
	$gsPresent       = rex_addon::isAvailable('global_settings');
	$gsVersion       = $gsPresent ? $REX['ADDON']['version']['global_settings'] : '0.0';
}

if (!$devUtilsPresent || version_compare($devUtilsVersion, '1.2.4', '<')) {
	$REX['ADDON']['installmsg']['frontenduser'] = 'Bitte installieren &amp; aktivieren Sie vor der Installation das Developer Utils-AddOn (>= 1.2.4).';
}
elseif (!$gsPresent || version_compare($gsVersion, '3.0', '<')) {
	$REX['ADDON']['installmsg']['frontenduser'] = 'Bitte installieren &amp; aktivieren Sie vor der Installation das Global Settings-AddOn (>= v3.0).';
}
else {
	require_once $REX['INCLUDE_PATH'].'/addons/frontenduser/classes/_WV16/Extensions.php';
	$success = _WV16_Extensions::addonInstalled(array('subject' => 'global_settings'));

	if (!$success) {
		$REX['ADDON']['installmsg']['frontenduser'] = 'Es trat ein Fehler beim Anlegen der Global Settings auf.';
	}

	$REX['ADDON']['install']['frontenduser'] = (int) $success;
}
