<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

define('_WV_FRONTENDUSER_PATH', rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

$container = sly_Core::getContainer();
$backend   = sly_Core::isBackend();

// load language file
if ($backend) {
	$container['sly-i18n']->appendFile(_WV_FRONTENDUSER_PATH.'lang');
}

// init events
$dispatcher = $container['sly-dispatcher'];
$dispatcher->addListener('SLY_CACHE_CLEARED',           array('wv\FrontendUser\EventHandler', 'onCacheCleared'));
$dispatcher->addListener('SLY_SYSTEM_CACHES',           array('wv\FrontendUser\EventHandler', 'onSystemCaches'));
$dispatcher->addListener('SLY_BACKEND_NAVIGATION_INIT', array('wv\FrontendUser\EventHandler', 'onBackendNavInit'));

$dispatcher->addListener(sly\Assets\Service::EVENT_IS_PROTECTED_ASSET, array('wv\FrontendUser\EventHandler', 'onIsProtectedAsset'));

// rebuild complete metadata table when importing a dump
$dispatcher->addListener('SLY_DB_IMPORTER_AFTER', array('wv\FrontendUser\EventHandler', 'onDatabaseImportAfter'));

// sync attributes and types
if (sly_Core::isDeveloperMode()) {
	$dispatcher->addListener('SLY_ADDONS_LOADED', array('wv\FrontendUser\EventHandler', 'onAddonsLoaded'));
}

// define controllers
$container['sly-backend-controller-frontenduser'] = function() {
	return new wv\FrontendUser\Controller\UserController();
};

$container['sly-backend-controller-frontenduser_groups'] = function() {
	return new wv\FrontendUser\Controller\GroupController();
};

$container['sly-backend-controller-frontenduser_exports'] = function() {
	return new wv\FrontendUser\Controller\ExportController();
};
