<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Service_Attribute extends WV_Service_Property {
	public static function loadAll($forceRefresh = false) {
		static $properties = null;

		if ($properties === null || $forceRefresh) {
			$config     = sly_Core::config();
			$attributes = $config->get('frontenduser/attributes', array());
			$cacheFile  = sly_Service_Factory::getAddOnService()->internalFolder('frontenduser').'/attributes.php';
			$oldState   = null;

			if (file_exists($cacheFile)) {
				include $cacheFile;
				$oldState = $known;
			}

			$service    = new self();
			$properties = $service->loadFromArray($attributes, $oldState);

			if ($oldState !== $attributes) {
				file_put_contents($cacheFile, '<?php $known = '.var_export($attributes, true).';');
			}
		}

		return $properties;
	}

	public function loadFromArray(array $data, array $oldData = null) {
		$cache  = sly_Core::cache();
		$struct = $cache->get('frontenduser', 'attributes', null);

		if ($struct === null || $data !== $oldData) {
			$struct = parent::loadFromArray($data, $oldData);
			$cache->set('frontenduser', 'attributes', $struct);
		}

		return $struct;
	}

	protected function onNew(WV_Property $property) {
		$utypes = $property->getUserTypes();

		// Standardwert übernehmen

		if (!empty($utypes)) {
			$markers = '\''.implode('\',\'', $utypes).'\'';
			$sql     = WV_SQL::getInstance();
			$name    = $property->getName();
			$default = $property->getDefault();

			// 1. Selektiere all diejenigen Benutzer, die schon Werte (= Sets) haben.

			$select1 =
				'SELECT DISTINCT user_id,?,set_id,? '.
				'FROM ~wv16_user_values uv, ~wv16_users u '.
				'WHERE uv.user_id = u.id AND u.type IN ('.$markers.') AND set_id >= 0';

			// 2. Selektiere all diejenigen Benutzer, die noch keine Werte haben.
			// Das kann z.B. auftreten, wenn die Benutzer zu Typen gehören, die noch
			// keine Attribute hatten.

			$select2 =
				'SELECT DISTINCT id,?,1,? FROM ~wv16_users u '.
				'WHERE u.type IN ('.$markers.') AND u.id NOT IN '.
				'(SELECT DISTINCT user_id FROM ~wv16_user_values)';

			// 3. Vereinige diese beiden Mengen

			$select = $select1.' UNION '.$select2;

			// 4. Verwende dieses SELECT, um damit das INSERT-Statement zu befeuern.

			$query = 'INSERT IGNORE INTO ~wv16_user_values (user_id,attribute,set_id,value) '.$select;
			$sql->query($query, array($name, $default, $name, $default), '~');
		}

		WV16_Users::clearCache();
	}

	protected function onDeleted(WV_Property $property) {
		$sql  = WV_SQL::getInstance();
		$name = $property->getName();

		$sql->query('DELETE FROM ~wv16_user_values WHERE attribute = ?', $name, '~');
		WV16_Users::clearCache();
	}

	protected function onChangedDatatype(WV_Property $oldVersion, WV_Property $newVersion) {
		// alle Benutzerdaten löschen
		$this->onDeleted($oldVersion);

		// Standardwerte neu eintragen
		$this->onNew($newVersion);

		WV16_Users::clearCache();
	}

	protected function onChangedParameters(WV_Property $oldVersion, WV_Property $newVersion) {
		// Wenn sich die Artikeltypen geändert haben, müssen wir noch flugs
		// die Metadaten löschen/hinzufügen, bevor wir ein Update der Daten
		// ausführen.

		$this->onChanged($oldVersion, $newVersion);

		// Jetzt könnnen die bestehenden Daten aktualisiert werden.

		$oldParams = $oldVersion->getParams();
		$newParams = $newVersion->getParams();

		try {
			$actionsToTake = $oldVersion->datatypeCall('getIncompatibilityUpdateStatement', array($oldParams, $newParams));
		}
		catch (Exception $e) {
			return;
		}

		$prefix    = WV_SQL::getPrefix();
		$sql       = WV_SQL::getInstance();
		$attribute = $oldVersion->getName();

		foreach ($actionsToTake as $action) {
			list ($type, $what, $where) = $action;
			$what  = str_replace('$$$value_column$$$', 'value', $what);
			$where = str_replace('$$$value_column$$$', 'value', $where);
			$where = 'attribute = '.$sql->quote($attribute).' AND ('.$where.')';

			switch ($type) {
				case 'DELETE':
					$sql->query('DELETE FROM '.$prefix.'wv16_user_values WHERE '.$where);
					break;

				case 'UPDATE':
					$sql->query('UPDATE '.$prefix.'wv16_user_values SET '.$what.' WHERE '.$where);
					break;

				default:
					trigger_error('Unbekannte Aktion "'.$type.'" beim Aktualisieren des Attributs '.$attribute.'!', E_USER_WARNING);
			}
		}

		WV16_Users::clearCache();
	}

	protected function onChanged(WV_Property $oldVersion, WV_Property $newVersion) {
		$oldTypes = $oldVersion->getUserTypes();
		$newTypes = $newVersion->getUserTypes();

		if ($oldTypes !== $newTypes) {
			$addedTypes   = array_diff($newTypes, $oldTypes);
			$deletedTypes = array_diff($oldTypes, $newTypes);
			$sql          = WV_SQL::getInstance();
			$name         = $newVersion->getName();

			// Daten von den nicht mehr verknüpften Benutzern löschen

			if (!empty($deletedTypes)) {
				$sql->query('DELETE v FROM ~wv16_user_values v, ~wv16_users u '.
					'WHERE v.user_id = u.id AND u.type IN (\''.implode('\',\'', $deletedTypes).'\') AND v.set_id >= 0',
					null, '~'
				);
			}

			// Daten zu den neu verknüpften Benutzern hinzufügen

			if (!empty($addedTypes)) {
				$sql->query(
					'INSERT IGNORE INTO ~wv16_user_values '.
					'SELECT v.user_id,?,v.set_id,? FROM ~wv16_user_values v, ~wv16_users u '.
					'WHERE v.user_id = u.id AND u.type IN (\''.implode('\',\'', $addedTypes).'\') AND v.set_id >= 0',
					array($name, $newVersion->getDefault()) , '~'
				);
			}
		}

		WV16_Users::clearCache();
	}

	protected function createProperty($name, array $property) {
		return new _WV16_Attribute($name, $property);
	}

	public function rebuild(array $attributes) {
		$sql   = WV_SQL::getInstance();
		$names = array_keys($attributes);
		$names = array_map(array($sql, 'quote'), $names);

		// delete all uservalues which don't have a valid attribute

		$sql->query(
			'DELETE FROM ~wv16_user_values WHERE 1'.(empty($names) ? '' : ' AND attribute NOT IN ('.implode(',', $names).')'),
			null, '~'
		);

		// check if the linked users still exist

		if (!empty($names)) {
			foreach ($attributes as $name => $attribute) {
				$uTypes = $attribute->getUserTypes();

				// If this attribute is not assigned to *any* type, delete all occurences
				// of it. Otherwise just delete those uservalues which belong to users
				// that don't have the required user types.

				if (empty($uTypes)) {
					$users = '';
				}
				else {
					$uTypes = array_map(array($sql, 'quote'), $uTypes);
					$users  = ' AND user_id NOT IN (SELECT id FROM ~wv16_users WHERE `type` IN ('.implode(',', $uTypes).'))';
				}

				$sql->query('DELETE FROM ~wv16_user_values WHERE attribute = ?'.$users, $name, '~');
			}
		}

		// add missing database rows
		// (doing this after the deletion makes the DELETEs a bit faster in worst
		// case scenarios where just everything is missing and has to be inserted)

		foreach ($attributes as $attribute) {
			$this->onNew($attribute); // run INSERT IGNORE
		}
	}
}
