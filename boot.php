<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

define('_WV16_PATH', rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

// AddOn-Konfiguration

if (sly_Core::isBackend()) {
	sly_Core::getI18N()->appendFile(_WV16_PATH.'lang');
}

sly_Loader::addLoadPath(_WV16_PATH.'lib');

// init events

$dispatcher = sly_Core::dispatcher();
$dispatcher->register('SLY_CACHE_CLEARED', array('WV16_Users', 'onClearCache'));
$dispatcher->register('SLY_SYSTEM_CACHES', array('WV16_Users', 'systemCacheList'));
$dispatcher->register(sly_Service_Asset::EVENT_IS_PROTECTED_ASSET, array('WV16_Users', 'isProtectedListener'));

if (sly_Core::isBackend()) {
	$dispatcher->register('SLY_ADDONS_LOADED', array('WV16_Users', 'initMenu'));
}

// rebuild complete metadata table when importing a dump
$dispatcher->register('SLY_DB_IMPORTER_AFTER', array('WV16_Users', 'rebuildUserdata'));

// Attribute & Typen synchronieren

if (sly_Core::isDeveloperMode()) {
	$dispatcher->register('SLY_ADDONS_LOADED', array('WV16_Users', 'syncYAML'));
}
