<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Group extends WV_Object {
	protected $id;
	protected $name;
	protected $title;
	protected $parentID;

	private static $instances = array();

	public static function getInstance($groupID) {
		$groupID = (int) $groupID;

		if (empty(self::$instances[$groupID])) {
			$callback = array(__CLASS__, '_getInstance');
			$instance = self::getFromCache('frontenduser.internal.groups', $groupID, $callback, $groupID);

			self::$instances[$groupID] = $instance;
		}

		return self::$instances[$groupID];
	}

	protected static function _getInstance($id) {
		return new self($id);
	}

	private function __construct($id) {
		$sql  = WV_SQL::getInstance();
		$data = $sql->fetch('*', 'wv16_groups', 'id = ?', $id);

		if (empty($data)) {
			throw new WV16_Exception('Die Gruppe #'.$id.' konnte nicht gefunden werden!');
		}

		$this->id       = (int) $data['id'];
		$this->name     = $data['name'];
		$this->title    = $data['title'];
		$this->parentID = (int) $data['parent_id'];
	}

	public static function getIDForName($group) {
		if (sly_Util_String::isInteger($group)) {
			return (int) $group;
		}

		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.internal.groups';
		$cacheKey  = sly_Cache::generateKey('id_for_name', $group);

		$id = $cache->get($namespace, $cacheKey, -1);

		if ($id < 0) {
			$sql = WV_SQL::getInstance();
			$id  = $sql->fetch('id', 'wv16_groups', 'name = ?', $group);
			$id  = $id === false ? -1 : (int) $id;

			$cache->set($namespace, $cacheKey, $id);
		}

		return (int) $id;
	}

	public static function exists($group) {
		$groupID = self::getIDForName($group);
		return $groupID > 0;
	}

	public function getName()  { return $this->name;  }
	public function getID()    { return $this->id;    }
	public function getTitle() { return $this->title; }
}
