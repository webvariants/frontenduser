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

class Group extends \WV_Object {
	protected $name;
	protected $title;

	private static $instances = array();

	public static function getInstance($group) {
		if (empty(self::$instances[$group])) {
			$callback = array(__CLASS__, '_getInstance');
			$instance = self::getFromCache('frontenduser.internal.groups', $group, $callback, $group);

			self::$instances[$group] = $instance;
		}

		return self::$instances[$group];
	}

	protected static function _getInstance($name) {
		return new self($name);
	}

	private function __construct($name) {
		$sql  = \WV_SQL::getInstance();
		$data = $sql->fetch('*', 'wv16_groups', 'name = ?', $name);

		if (empty($data)) {
			throw new Exception('Die Gruppe '.$name.' konnte nicht gefunden werden!');
		}

		$this->name  = $data['name'];
		$this->title = $data['title'];
	}

	/**
	 * @return Group
	 */
	public static function create($name, $title) {
		$service = new Service\GroupService();
		$service->create($name, $title);

		return self::getInstance($name);
	}

	public function update() {
		$service = new Service\GroupService();
		$service->update($this);
	}

	public function delete() {
		$service = new Service\GroupService();
		$service->delete($this);
	}

	/**
	 * @return boolean  true, falls ja, sonst false
	 */
	public static function exists($name) {
		$service = new Service\GroupService();
		return $service->exists($name);
	}

	public function getName() {
		return $this->name;
	}

	public function getTitle() {
		return $this->title;
	}

	public function setTitle($title) {
		$this->title = trim($title);
	}
}
