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
	
	static $classes = array(
		'_WV16'             => 'internal/class.frontenduser.php',
		'_WV16_Attribute'   => 'internal/class.attribute.php',
		'_WV16_UserType'    => 'internal/class.usertype.php',
		'_WV16_UserValue'   => 'internal/class.uservalue.php',
		'_WV16_Group'       => 'internal/class.group.php',
		'_WV16_DataHandler' => 'internal/class.datahandler.php',
		'_WV16_User'        => 'internal/class.user.php',
		
		'WV16_Users'     => 'class.users.php',
		'WV16_Mailer'    => 'class.mailer.php',
		'WV16_Exception' => 'class.exception.php'
	);
	
	if (isset($classes[$className])) {
		require_once _WV16_PATH.'classes/'.$classes[$className];
		return '';
	}
}

rex_register_extension('__AUTOLOAD', '_wv16_autoload');

// Initialisierungen

require_once _WV16_PATH.'classes/internal/class.extensions.php';
rex_register_extension('DEVUTILS_INIT', array('_WV16_Extensions', 'plugin'));

// Dateien rausschicken, die über FrontendUser geschützt sind.

if (WV_Redaxo::isFrontend() && isset($_REQUEST['wv16_file'])) {
	$filename = $_REQUEST['wv16_file'];
	$media    = OOMedia::getMediaByFilename($filename);
	
	if (OOMedia::isValid($media)) {
		if (!WV16_Users::isProtected($media) || (WV16_Users::isLoggedIn() && WV16_Users::getCurrentUser()->canAccess($media))) {
			header('Content-Type: '.$media->getType());
			readfile('files/'.$media->getFileName());
		}
		else {
			header('HTTP/1.1 403 Forbidden');
		}
	}
	else {
		header('HTTP/1.1 404 Not Found');
	}
	
	exit;
}
