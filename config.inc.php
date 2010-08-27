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

if ($REX['SETUP'] || defined('_WV16_PATH')) return;
define('_WV16_PATH', $REX['INCLUDE_PATH'].'/addons/frontenduser/');

// AddOn-Konfiguration

if ($REX['REDAXO']) {
	$I18N->appendFile(_WV16_PATH.'lang');
}

if (!defined('IS_SALLY')) {
	$REX['ADDON']['page']['frontenduser']        = 'frontenduser';
	$REX['ADDON']['name']['frontenduser']        = $REX['REDAXO'] ? $I18N->msg('frontenduser_title') : 'Benutzerverwaltung';
	$REX['ADDON']['version']['frontenduser']     = file_get_contents(_WV16_PATH.'version');
	$REX['ADDON']['author']['frontenduser']      = 'Christoph Mewes';
	$REX['ADDON']['perm']['frontenduser']        = 'frontenduser[]';
	$REX['ADDON']['supportpage']['frontenduser'] = 'www.webvariants.de';
	$REX['ADDON']['requires']['frontenduser']    = array('developer_utils', 'global_settings');
	$REX['PERM'][] = 'frontenduser[]';
}

// Autoloading

if (defined('IS_SALLY')) {
	// Wir müssen zuerst den Pfad zu den internen Klassen anlegen, da für
	// _WV16_User sonst [WV16, User] erzeugt und dann immer die public Klasse
	// geladen werden würde.

	sly_Loader::addLoadPath(_WV16_PATH.'classes/_WV16', '_WV16');
	sly_Loader::addLoadPath(_WV16_PATH.'classes');
}
else {
	require_once _WV16_PATH.'autoload.inc.php';
}

// Initialisierungen

rex_register_extension('DEVUTILS_INIT', array('_WV16_Extensions', 'plugin'));
rex_register_extension('ALL_GENERATED', array('WV16_Users', 'clearCache'));

// Dateien rausschicken, die über FrontendUser geschützt sind.

if (WV_Redaxo::isFrontend() && !empty($_REQUEST['wv16_file'])) {
	require_once _WV16_PATH.'proxy.inc.php';
}
