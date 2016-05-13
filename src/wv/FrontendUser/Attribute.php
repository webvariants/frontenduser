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

class Attribute extends \WV_Property {
	protected $userTypes;
	protected $visible;

	public function __construct($name, array $data) {
		parent::__construct($name, $data);

		$this->multilingual = false;
		$this->userTypes    = $this->getData('types', array());
		$this->visible      = (boolean) $this->getData('visible', true);
	}

	public function getID() {
		return sprintf('%u', crc32($this->name));
	}

	public function getUserTypes() {
		return $this->userTypes;
	}

	public function isVisible() {
		return $this->visible;
	}
}
