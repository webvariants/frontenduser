<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace wv\FrontendUser;

abstract class EventHandler {
	public static function onCacheCleared($subject = null) {
		$isSystem   = \sly_Core::getCurrentControllerName() === 'system';
		$controller = \sly_Core::getCurrentController();
		$selective  = $isSystem && method_exists($controller, 'isCacheSelected');
		$clearData  = !$selective || $controller->isCacheSelected('fu-data');
		$rebuild    = !$selective || $controller->isCacheSelected('fu-rebuild');

		if ($clearData) {
			Users::clearCache();
		}

		if ($rebuild) {
			Users::rebuildUserdata();
		}

		return true;
	}

	public static function onDatabaseImportAfter() {
		Users::rebuildUserdata();
	}

	public static function onAddonsLoaded() {
		Users::syncYAML();
	}

	public static function onIsProtectedAsset($isProtected, array $params) {
		// if someone else has already decied that this file is protected, do nothing
		if ($isProtected) return true;

		$file     = $params['file']; // e.g. "data/mediapool/foo.jpg" or (with image_resize) "data/mediapool/600w__foo.jpg"
		$basename = basename($file);

		// if the file is not stored in mediapool, it cannot be protected by FrontendUser
		if (!\sly_Util_String::startsWith($file, 'data/mediapool')) return false;

		// image_resize request?
		if (\sly_Service_Factory::getAddOnService()->isAvailable('image_resize')) {
			$result = \A2_Extensions::parseFilename($basename);
			if ($result) $basename = $result['filename']; // "600w__foo.jpg" -> "foo.jpg"
		}

		// find file in mediapool
		$fileObj = \sly_Util_Medium::findByFilename($basename);
		if ($fileObj === null) return false;

		// let the project decide whether the file is protected
		return \sly_Core::dispatcher()->filter('WV16_IS_FILE_PROTECTED', false, compact('fileObj'));
	}

	public static function onBackendNavInit($nav, array $params) {
		$user = $params['user'];

		if ($user) {
			$isAdmin = $user->isAdmin();
			$exports = \sly_Core::config()->get('frontenduser/exports', null);

			if ($isAdmin || $user->hasRight('frontenduser', 'users')) {
				$page = $nav->addPage('addon', 'frontenduser', t('frontenduser_title'));
				$page->addSubpage('frontenduser', t('users'));

				if ($isAdmin || $user->hasRight('frontenduser', 'groups')) {
					$page->addSubpage('frontenduser_groups', 'Gruppen');
				}

				if ($exports && ($isAdmin || $user->hasRight('frontenduser', 'groups'))) {
					$page->addSubpage('frontenduser_exports', 'Export');
				}
			}
		}
	}

	public static function onSystemCaches(\sly_Form_Select_Checkbox $select, array $params = array()) {
		$selected   = $select->getValue();
		$selected[] = 'fu-data';

		if (\sly_Core::isDeveloperMode()) {
			$selected[] = 'fu-rebuild';
		}

		$select->addValue('fu-data',    'Benutzerdaten-Cache leeren');
		$select->addValue('fu-rebuild', 'Benutzerdaten validieren & neu aufbauen');
		$select->setSelected($selected);

		return $select;
	}
}
