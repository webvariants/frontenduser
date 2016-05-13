<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace wv\FrontendUser\Controller;

use sly\Assets\Util;

abstract class BaseController extends \sly_Controller_Backend implements \sly_Controller_Interface {
	protected $errors = array();
	protected $init   = false;

	protected function getViewFolder() {
		return _WV_FRONTENDUSER_PATH.'views/';
	}

	protected function init() {
		if ($this->init) return;
		$this->init = true;

		$layout = \sly_Core::getLayout();
		$layout->addCSSFile(Util::addOnUri('webvariants/frontenduser', 'css/wv16.less'));
		$layout->addJavaScriptFile(Util::addOnUri('webvariants/frontenduser', 'js/frontenduser.js'));
		$layout->pageHeader(t('frontenduser_title'));
	}
}
