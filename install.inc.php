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

if (!rex_addon::isAvailable('developer_utils')) {
	$REX['ADDON']['installmsg']['frontenduser'] = 'Bitte installieren &amp; aktivieren Sie vor der Installation das Developer Utils-AddOn.';
}
else {
	$success = true;
	
	if (rex_addon::isAvailable('global_settings')) {
		require_once $REX['INCLUDE_PATH'].'/addons/frontenduser/classes/internal/class.extensions.php';
		$success = _WV16_Extensions::addonInstalled(array('subject' => 'global_settings'));
	}
	
	if (!$success) {
		$REX['ADDON']['installmsg']['frontenduser'] = 'Es trat ein Fehler beim Anlegen der Global Settings auf.';
	}
	
	$REX['ADDON']['install']['frontenduser'] = (int) $success;
}
