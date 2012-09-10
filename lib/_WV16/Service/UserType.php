<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Service_UserType {
	public static function loadAll($forceRefresh = false) {
		static $types = null;

		if ($types === null || $forceRefresh) {
			$config    = sly_Core::config();
			$curState  = $config->get('frontenduser/types', array());
			$cacheFile = sly_Service_Factory::getAddOnService()->internalDirectory('frontenduser').'/types.php';
			$oldState  = null;

			if (file_exists($cacheFile)) {
				include $cacheFile;
				$oldState = $known;
			}

			$service = new self();
			$types   = $service->loadFromArray($curState, $oldState);

			if ($oldState !== $curState) {
				file_put_contents($cacheFile, '<?php $known = '.var_export($curState, true).';');
			}
		}

		return $types;
	}

	public function loadFromArray(array $data, array $oldData = null) {
		$cache  = sly_Core::cache();
		$struct = $cache->get('frontenduser', 'types', null);

		if ($struct === null || $data !== $oldData) {
			$struct  = array();
			$oldData = $oldData === null ? $data : $oldData;

			if ($oldData === $data) {
				foreach ($oldData as $name => $type) {
					$struct[$name] = $this->createType($name, $type);
				}
			}
			else {
				$struct = $this->compareStates($oldData, $data);
			}

			$cache->set('frontenduser', 'types', $struct);
		}

		return $struct;
	}

	protected function compareStates($oldState, $newState) {
		$types = array();

		// iterate over the new list to use its order of types

		foreach ($newState as $name => $type) {
			$newObj = $this->createType($name, $type);

			// has a new type been added?
			if (!isset($oldState[$name])) {
				$this->onNew($newObj);
			}

			// has a type been changed?
			elseif ($oldState[$name] !== $type) {
				$oldObj = $this->createType($name, $oldState[$name]);
				$this->onChanged($oldObj, $newObj);
			}

			$types[$name] = $newObj;
		}

		// check for deleted types

		foreach ($oldState as $name => $type) {
			if (!isset($newState[$name])) {
				$obj = $this->createType($name, $type);
				$this->onDeleted($obj);
			}
		}

		return $types;
	}

	protected function onNew(_WV16_UserType $type) {
		// do nothing as no object can be affected by a new type only
		WV16_Users::clearCache();
	}

	protected function onDeleted(_WV16_UserType $type) {
		$name    = $type->getName();
		$default = _WV16_UserType::DEFAULT_NAME;
		$sql     = WV_SQL::getInstance();

		if ($name === $default) {
			throw new WV16_Exception('You cannot delete the default user type. Undo your change!');
		}

		// Welche Attribute gehörten zu diesem Typ?

		$attrThisType    = array_keys(WV16_Provider::getAttributes($name));
		$attrDefaultType = array_keys(WV16_Provider::getAttributes($default));
		$attrToDelete    = array_diff($attrThisType, $attrDefaultType);

		// Daten löschen

		$sql->query('UPDATE ~wv16_users SET `type` = ? WHERE `type` = ?', array($default, $name), '~');

		if (!empty($attrToDelete)) {
			$sql->query('DELETE FROM ~wv16_user_values WHERE attribute IN (\''.implode('\',\'', $attrToDelete).'\')', null, '~');
		}

		WV16_Users::clearCache();
	}

	protected function onChanged(_WV16_UserType $oldVersion, _WV16_UserType $newVersion) {
		// changes on types can only affect the title and nothing else...
		WV16_Users::clearCache();
	}

	protected function createType($name, $title) {
		return new _WV16_UserType($name, $title);
	}

	public function rebuild(array $types) {
		if (empty($types)) return;

		// Find users not belonging to the existing types and turn
		// them over to the dark side, aka the default usertype.

		$sql     = WV_SQL::getInstance();
		$types   = array_map(array($sql, 'quote'), array_keys($types));
		$userIDs = $sql->getArray('SELECT id FROM ~wv16_users WHERE `type` NOT IN ('.implode(',', $types).')', null, '~');

		foreach ($userIDs as $userID) {
			$user = WV16_Factory::getUserByID($userID);
			$user->setUserType(_WV16_UserType::DEFAULT_NAME);
			$user->update();
		}
	}
}
