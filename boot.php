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
$dispatcher->register('SLY_CACHE_CLEARED', array('wv\FrontendUser\Users', 'onClearCache'));
$dispatcher->register('SLY_SYSTEM_CACHES', array('wv\FrontendUser\Users', 'systemCacheList'));

$dispatcher->register(sly\Assets\Service::EVENT_IS_PROTECTED_ASSET, array('wv\FrontendUser\Users', 'isProtectedListener'));

if ($backend) {
	$dispatcher->register('SLY_ADDONS_LOADED', array('wv\FrontendUser\Users', 'initMenu'));
}

// rebuild complete metadata table when importing a dump
$dispatcher->register('SLY_DB_IMPORTER_AFTER', array('wv\FrontendUser\Users', 'rebuildUserdata'));

// sync attributes and types
if (sly_Core::isDeveloperMode()) {
	$dispatcher->register('SLY_ADDONS_LOADED', array('wv\FrontendUser\Users', 'syncYAML'));
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
