<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Attribute extends WV_Property {
	protected $userTypes;  ///< array
	protected $visible;    ///< boolean

	public function __construct($name, array $data) {
		parent::__construct($name, $data);

		$this->multinigual = false;
		$this->userTypes   = $this->getData('types', array());
		$this->visible     = (boolean) $this->getData('visible', true);
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

	public function deserialize($raw) {
		$datatype = $this->getDatatypeID();
		$params   = $this->getParams();

		return WV_Datatype::call($datatype, 'deserializeValue', array($raw, $params));
	}
}
