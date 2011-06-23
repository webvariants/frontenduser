<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
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

sly_Core::dispatcher()->register('ALL_GENERATED', array('WV16_Users', 'clearCache'));
sly_Core::dispatcher()->register(sly_Service_Asset::EVENT_IS_PROTECTED_ASSET, array('WV16_Users', 'isProtectedListener'));

// Attribute & Typen synchronieren

if (sly_Core::isDeveloperMode()) {
	sly_Core::dispatcher()->register('ADDONS_INCLUDED', array('WV16_Users', 'syncYAML'));
}
