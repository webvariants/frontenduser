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

if ($REX['SETUP']) return;

// Wegen Abhängigkeiten kann das AddOn bereits von anderen
// AddOns eingebunden worden sein und muss dann den Aufruf
// von Redaxo ablehnen. Redaxo verwendet kein include_once.

if (defined('_WV16_PATH')) return;

// MetaInfoEx wird später von Redaxo eingebunden, da Redaxo
// alphabetisch sortiert. Daher müssen wir uns selber darum
// kümmern.

require $REX['INCLUDE_PATH'].'/addons/metainfoex/config.inc.php';

// AddOn-Konfiguration

$REX['ADDON']['page']['frontenduser']        = 'frontenduser';
$REX['ADDON']['name']['frontenduser']        = 'Benutzerverwaltung';
$REX['ADDON']['version']['frontenduser']     = '1.0.1';
$REX['ADDON']['author']['frontenduser']      = 'Christoph Mewes';
$REX['ADDON']['perm']['frontenduser']        = 'frontenduser[]';
$REX['ADDON']['supportpage']['frontenduser'] = 'www.webvariants.de';

$REX['PERM'][] = 'frontenduser[]';

// Bibliothek einbinden

define('_WV16_PATH', $REX['INCLUDE_PATH'].'/addons/frontenduser/');

include_once _WV16_PATH.'classes/internal/class.wv16.php';
include_once _WV16_PATH.'classes/internal/class.wv16attribute.php';
include_once _WV16_PATH.'classes/internal/class.wv16usertype.php';
include_once _WV16_PATH.'classes/internal/class.wv16uservalue.php';
include_once _WV16_PATH.'classes/internal/class.wv16group.php';
include_once _WV16_PATH.'classes/internal/class.wv16dataprovider.php';
include_once _WV16_PATH.'classes/internal/class.wv16user.php';
include_once _WV16_PATH.'classes/internal/class.wv16extensions.php';

include_once _WV16_PATH.'classes/class.wv16users.php';
include_once _WV16_PATH.'classes/class.wv16mailer.php';
include_once _WV16_PATH.'classes/class.phpmailer.php';

// Initialisierungen

_WV16_Extensions::plugin(isset($page) ? $page : '', isset($mode) ? $mode : '');
_WV16_Extensions::sendFiles();

if ($REX['REDAXO']) {
	rex_register_extension('WV2_INIT_PERMISSIONS', array('_WV16','initPermissions'));
	_WV2::initUserPermissions();
}

if (isset($_REQUEST['wv16_file'])) {
	$filename = $_REQUEST['wv16_file'];
	$media    = OOMedia::getMediaByFilename($filename);
	if (OOMedia::isValid($media)) {
		if (!WV16_Users::isProtected($media) || (WV16_Users::isLoggedIn() && WV16_Users::getCurrentUser()->canAccess($media))) {
			header('Content-Type: '.$media->getType());
			readfile('files/'.$media->getFileName());
		}
		else {
			header("HTTP/1.1 403 Forbidden");
		}
	}
	else {
		header("HTTP/1.1 404 Not Found");
	}
	exit;
}
