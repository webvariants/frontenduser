<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_UserType {
	const DEFAULT_NAME = 'default';

	protected $name;   ///< string  der interne Name
	protected $title;  ///< string  der angezeigte Titel

	public function __construct($name, $title) {
		if (empty($title)) {
			throw new WV16_Exception('User types require a title.');
		}

		$this->name  = trim($name);
		$this->title = trim($title);
	}

	public function getName()  { return $this->name;  }
	public function getTitle() { return $this->title; }

	public function getAttributes() {
		return WV16_Provider::getAttributes($this->name);
	}
}
