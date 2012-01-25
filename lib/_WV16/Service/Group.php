<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Service_Group extends WV_Object {
	public function create($name, $title) {
		$sql   = WV_SQL::getInstance();
		$name  = trim($name);
		$title = trim($title);

		if (empty($name))  throw new WV16_Exception('Der interne Name darf nicht leer sein.');
		if (empty($title)) throw new WV16_Exception('Die Bezeichnung darf nicht leer sein.');

		if ($sql->count('wv16_groups', 'LOWER(name) = ?', strtolower($name)) != 0) {
			throw new WV16_Exception('Dieser interne Name ist bereits vergeben.');
		}

		$params = array($name, $title);
		self::transactionGuard(array($this, '_create'), $params, 'WV16_Exception');
	}

	protected function _create($name, $title) {
		$sql = WV_SQL::getInstance();
		$sql->query('INSERT INTO ~wv16_groups (name,title) VALUES (?,?)', array($name, $title), '~');

		$this->clearCache();
	}

	public function update(_WV16_Group $group) {
		if (strlen($group->getTitle()) === 0) throw new WV16_Exception('Die Bezeichnung darf nicht leer sein.');
		self::transactionGuard(array($this, '_update'), $group, 'WV16_Exception');
	}

	protected function _update(_WV16_Group $group) {
		$sql   = WV_SQL::getInstance();
		$name  = $group->getName();
		$title = $group->getTitle();

		$sql->query('UPDATE ~wv16_groups SET title = ? WHERE name = ?', array($title, $name), '~');
		$this->clearCache();
	}

	public function delete(_WV16_Group $group) {
		return self::transactionGuard(array($this, '_delete'), $group, 'WV16_Exception');
	}

	protected function _delete(_WV16_Group $group) {
		$sql  = WV_SQL::getInstance();
		$name = $group->getName();

		$sql->query('DELETE FROM ~wv16_groups WHERE name = ?', $name, '~');
		$sql->query('DELETE FROM ~wv16_user_groups WHERE `group` = ?', $name, '~');

		$this->clearCache();
	}

	/**
	 * @return boolean  true, falls ja, sonst false
	 */
	public function exists($name) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.groups';
		$cacheKey  = sly_Cache::generateKey('mapping', $name);

		if ($cache->exists($namespace, $cacheKey)) {
			return true;
		}

		$sql  = WV_SQL::getInstance();
		$name = $sql->fetch('name', 'wv16_groups', 'LOWER(name) = ?', strtolower($name));

		if ($name !== false) {
			$cache->set($namespace, $cacheKey, $name);
			return true;
		}

		return false;
	}

	private function clearCache() {
		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);
	}
}
