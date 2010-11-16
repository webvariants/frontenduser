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

if (sly_Core::config()->get('SETUP') || defined('_WV16_PATH')) return;
define('_WV16_PATH', SLY_INCLUDE_PATH.'/addons/frontenduser/');

// AddOn-Konfiguration

if (sly_Core::isBackend()) {
	sly_Core::getI18N()->appendFile(_WV16_PATH.'lang');
}

sly_Loader::addLoadPath(_WV16_PATH.'lib/_WV16', '_WV16');
sly_Loader::addLoadPath(_WV16_PATH.'lib');

// Initialisierungen

$dispatcher = sly_Core::dispatcher();
$dispatcher->register('PAGE_CHECKED', array('_WV16_Extensions', 'plugin'));
$dispatcher->register('ALL_GENERATED', array('WV16_Users', 'clearCache'));

// Dateien rausschicken, die über FrontendUser geschützt sind.

if (!sly_Core::isBackend() && !empty($_REQUEST['wv16_file'])) {
	require_once _WV16_PATH.'proxy.inc.php';
}
