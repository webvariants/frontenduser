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

class UserType {
	const DEFAULT_NAME = 'default';

	protected $name;
	protected $title;

	public function __construct($name, $title) {
		if (empty($title)) {
			throw new Exception('User types require a title.');
		}

		$this->name  = trim($name);
		$this->title = trim($title);
	}

	public function getName() {
		return $this->name;
	}

	public function getTitle() {
		return $this->title;
	}

	public function getAttributes() {
		return Provider::getAttributes($this->name);
	}
}
