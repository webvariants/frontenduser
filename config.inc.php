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

if ($REX['SETUP'] || defined('_WV16_PATH')) return;
define('_WV16_PATH', $REX['INCLUDE_PATH'].'/addons/frontenduser/');

// AddOn-Konfiguration

$REX['ADDON']['page']['frontenduser']        = 'frontenduser';
$REX['ADDON']['name']['frontenduser']        = 'Benutzerverwaltung';
$REX['ADDON']['version']['frontenduser']     = file_get_contents(_WV16_PATH.'version');
$REX['ADDON']['author']['frontenduser']      = 'Christoph Mewes';
$REX['ADDON']['perm']['frontenduser']        = 'frontenduser[]';
$REX['ADDON']['supportpage']['frontenduser'] = 'www.webvariants.de';
$REX['PERM'][] = 'frontenduser[]';

// Autoloading

function _wv16_autoload($params)
{
	$className = $params['subject'];
	require _WV16_PATH.'autoload.inc.php';
}

rex_register_extension('__AUTOLOAD', '_wv16_autoload');

// Initialisierungen

require_once _WV16_PATH.'classes/internal/class.extensions.php';
rex_register_extension('DEVUTILS_INIT', array('_WV16_Extensions', 'plugin'));

// Dateien rausschicken, die über FrontendUser geschützt sind.

if (WV_Redaxo::isFrontend() && !empty($_REQUEST['wv16_file'])) {
	require_once _WV16_PATH.'proxy.inc.php';
}
