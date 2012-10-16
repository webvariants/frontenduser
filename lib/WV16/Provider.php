<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class WV16_Provider {
	public static function getTotalUsers($where = '1') {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('total_users', $where);
		$total     = $cache->get($namespace, $cacheKey, -1);

		if ($total < 0) {
			$sql   = WV_SQL::getInstance();
			$total = $sql->count('wv16_users', $where);
			$total = $total === false ? -1 : (int) $total;

			$cache->set($namespace, $cacheKey, $total);
		}

		return $total;
	}

	public static function getUsers($where, $orderBy = 'id', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('users_by', $where, $orderBy, $direction, $offset, $max);

		$users = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($users)) {
			$sql    = WV_SQL::getInstance();
			$query  = 'SELECT id FROM ~wv16_users WHERE '.$where.' ORDER BY '.$orderBy.' '.$direction;
			$max    = $max < 0 ? '18446744073709551615' : (int) $max;
			$query .= ' LIMIT '.$offset.','.$max;

			$users = $sql->getArray($query, array(), '~');
			$cache->set($namespace, $cacheKey, $users);
		}

		$result = array();

		foreach ($users as $userID) {
			$result[$userID] = _WV16_User::getInstance($userID);
		}

		return $result;
	}

	public static function getUsersInGroup($group, $orderBy = 'id', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('users_by', $group, $orderBy, $direction, $offset, $max);

		$users = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($users)) {
			$sql    = WV_SQL::getInstance();
			$query  =
				'SELECT id FROM ~wv16_users u LEFT JOIN ~wv16_user_groups ug ON u.id = ug.user_id '.
				'WHERE `group` = ? ORDER BY '.$orderBy.' '.$direction;

			if ($offset > 0 || $max < 0) {
				$max    = $max < 0 ? '18446744073709551615' : (int) $max;
				$query .= ' LIMIT '.$offset.','.$max;
			}

			$users = $sql->getArray($query, $group, '~');
			$cache->set($namespace, $cacheKey, $users);
		}

		$result = array();

		foreach ($users as $userID) {
			$result[$userID] = _WV16_User::getInstance($userID);
		}

		return $result;
	}

	public static function getGroups($orderBy = 'title', $direction = 'asc', $offset = 0, $max = -1) {
		$direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';
		$offset    = abs((int) $offset);
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('groups_by', $orderBy, $direction, $offset, $max);

		$groups = $cache->get($namespace, $cacheKey, -1);

		if (!is_array($groups)) {
			$sql    = WV_SQL::getInstance();
			$query  = 'SELECT name FROM ~wv16_groups WHERE 1 ORDER BY '.$orderBy.' '.$direction;

			if ($offset > 0 || $max < 0) {
				$max    = $max < 0 ? '18446744073709551615' : (int) $max;
				$query .= ' LIMIT '.$offset.','.$max;
			}

			$groups = $sql->getArray($query, array(), '~');
			$cache->set($namespace, $cacheKey, $groups);
		}

		$result = array();

		foreach ($groups as $group) {
			$result[$group] = _WV16_Group::getInstance($group);
		}

		return $result;
	}

	/**
	 * Benutzertypen ermitteln
	 *
	 * @return array  assoziatives Array (name => title)
	 */
	public static function getUserTypes() {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$data      = $cache->get($namespace, 'types', false);

		if (!is_array($data)) {
			$data = _WV16_Service_UserType::loadAll();
			$cache->set($namespace, 'types', $data);
		}

		return $data;
	}

	/**
	 * Benutzertypen ermitteln
	 *
	 * @return array  eine Liste von _WV16_Attribute-Objekten
	 */
	public static function getAttributes($userType = null) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('attributes', $userType);
		$data      = $cache->get($namespace, $cacheKey, false);

		if (!is_array($data)) {
			$data = _WV16_Service_Attribute::loadAll();

			if ($userType !== null) {
				foreach ($data as $name => $attribute) {
					if (!in_array($userType, $attribute->getUserTypes())) {
						unset($data[$name]);
					}
				}
			}

			$cache->set($namespace, $cacheKey, $data);
		}

		return $data;
	}

	/**
	 * Benutzeranzahl ermitteln
	 *
	 * @param  string $userType  der interne Name des Typs
	 * @return int               die Anzahl der zugehörigen Artikel
	 */
	public static function getUserCountByType($userType) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.counts';
		$cacheKey  = sly_Cache::generateKey('users_by_type', $userType);
		$count     = $cache->get($namespace, $cacheKey, -1);

		if ($count < 0) {
			$sql   = WV_SQL::getInstance();
			$count = $sql->count('wv16_users', '`type` = ?', $userType);

			$cache->set($namespace, $cacheKey, $count);
		}

		return $count;
	}

	/**
	 * Benutzerliste ermitteln
	 *
	 * @param  string $userType     der Benutzertyp
	 * @param  string $sortby       das Sortierkriterium (aus der Relation article oder wv2_article_type)
	 * @param  string $direction    die Sortierrichtung
	 * @param  string $limitClause  eine optionale "LIMIT a,b"-Angabe
	 * @return array                Liste von passenden _WV16_User-Objekten
	 */
	public static function getUsersByType($userType, $sortby = 'login', $direction = 'ASC', $limitClause = '') {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('users_by_type', $userType, $sortby, $direction, $limitClause);
		$users     = $cache->get($namespace, $cacheKey, false);
		$return    = array();

		if (!is_array($users)) {
			$sql   = WV_SQL::getInstance();
			$users = $sql->getArray(
				'SELECT id FROM ~wv16_users WHERE `type` = ? ORDER BY '.$sortby.' '.$direction.' '.$limitClause,
				$userType, '~'
			);

			$cache->set($namespace, $cacheKey, $users);
		}

		foreach ($users as $userID) {
			$return[$userID] = _WV16_User::getInstance($userID);
		}

		return $return;
	}

	/**
	 * Passende Benutzer anhand ihrer Attribute ermitteln
	 *
	 * Als Sortierkriterium muss eine Angabe der Form "tabelle.spalte" angegeben
	 * werden. Mögliche Tabellen sind wv16_user_values und wv16_users.
	 *
	 * Wird eine nicht bekannte Tabelle angegeben, wird die Angabe einfach
	 * ignoriert.
	 *
	 * @param  string $attribute  Attribut-Name
	 * @param  string $userType   der Name des Artikeltyps oder null, falls alle
	 * @param  int    $setID      ID des ValueSets, in dem gesucht werden soll
	 * @param  string $value      der gesuchte Attributwert oder null, falls egal
	 * @param  int    $operator   Vergleichsoperator für SQL-Abfrage
	 * @param  string $sort       eine optionale "ORDER BY"-Klausel (ohne "ORDER BY")
	 * @return array              eine Liste von passenden Benutzern
	 */
	public static function getUsersWithAttribute($attribute, $userType = null, $setID = 1, $value = null, $operator = null, $sort = null) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('users_by_attribute', $attribute, $userType, $value, $operator, $sort);
		$users     = $cache->get($namespace, $cacheKey, false);

		if (!is_array($users)) {
			//////////////////////////////////////////////////////////////////////////
			// Objekte finden, die die gesuchte Metainformation besitzen
			// Ob der gesuchte Wert enthalten ist, prüfen später die Datentypen
			// selbstständig.

			$sortTable  = strpos($sort, '.') === false ? ''    : substr($sort, 0, strpos($sort,'.'));
			$sortColumn = strpos($sort, '.') === false ? $sort : substr($sort, strpos($sort,'.') + 1);

			switch ($sortTable) {
				case 'user_values': $sortTable = 'uv'; break;
				case 'users':       $sortTable = 'u';  break;
				default:            $sortTable = '';
			}

			$query =
				'SELECT uv.user_id FROM ~wv16_user_values uv '.
				'LEFT JOIN ~wv16_users u ON u.id = uv.user_id '.
				'WHERE uv.attribute = ? AND uv.set_id = ?';

			if (!is_null($value) && !is_null($operator)) {
				$query .= ' AND uv.value '.$operator.' '.$value;
			}

			$sql    = WV_SQL::getInstance();
			$return = array();
			$params = array($attribute, (int) $setID);

			if ($userType !== null) {
				if (is_array($userType)) {
					// "type IN ()" would be invalid SQL and yield no result anyway when used
					// in a big conjunction like we do here, so skip the query completely
					if (empty($userType)) return array();

					$userType = array_values(array_filter(array_unique(array_values($userType))));
					$query   .= ' AND `type` IN ('.WV_SQL::getMarkers($userType).')';
					$params   = array_merge($params, $userType);
				}
				else {
					$query   .= ' AND `type` = ?';
					$params[] = $userType;
				}
			}

			if ($sortTable) {
				$query .= ' ORDER BY '.$sortTable.'.'.$sortColumn;
			}

			$users = $sql->getArray($query, $params, '~');

			// Nichts gefunden? Und tschüss!

			if (empty($users)) {
				$cache->set($namespace, $cacheKey, array());
				return array();
			}

			// Daten cachen
			$cache->set($namespace, $cacheKey, $users);
		}

		$return = array();

		foreach ($users as $userID) {
			$return[$userID] = _WV16_User::getInstance($userID);
		}

		return $return;
	}

	/**
	 * Werte eines Attributs erfahren
	 *
	 * @param  string $attribute        das Attribut
	 * @param  bool   $getOnlyExisting  wenn true, werden nur die Werte zurückgegeben, die ein Attribut auch wirklich angenommen hat
	 * @return array                    eine Liste von Alternativen
	 */
	public static function getAttributeValueSet($attribute, $getOnlyExisting = false) {
		$attribute = WV16_Users::getAttribute($attribute);
		$params    = $attribute->getParams();

		// Da PHP keine Arrays zulässt, bei denen die Keys zwar
		// Strings, aber Zahlen sind ("8" wird immer zu 8 konvertiert),
		// muss der Datentyp explizit angeben, ob seine Liste assoziativ
		// oder normal zu behandeln ist.

		$datalist = array();
		$isAssoc  = $attribute->datatypeCall('usesAssociativeResults');

		if ($getOnlyExisting) {
			$data = self::getUserDataForAttribute($attribute);

			foreach ($data as $d) {
				$datalist = $isAssoc ? self::merge($datalist, $d['value']) : ($datalist + $d['value']);
			}

			$datalist = array_unique($datalist);
		}
		else {
			$datalist = $attribute->datatypeCall('extractValuesFromParams', array($params));
		}

		return $datalist;
	}

	/**
	 * Attributwerte über alle Benutzer ermitteln
	 *
	 * @param  string $attribute  das Attribut
	 * @param  string $userType   der Name des gesuchten Benutzertyps oder null für keine Angabe
	 * @param  int    $setID      ID des Valuesets
	 * @return array              Liste von Werten (mixed)
	 */
	public static function getUserDataForAttribute($attribute = null, $userType = null, $setID = null) {
		// Query vorbereiten

		$params = array();

		if ($userType === null) {
			$query = 'SELECT * FROM ~wv16_user_values WHERE 1'; // PPP: Pöööse Performance Probleme!
		}
		else {
			$query = 'SELECT uv.* FROM ~wv16_user_values uv LEFT JOIN ~wv16_users u ON uv.user_id = u.id WHERE 1';
		}

		// Parameter einsetzen

		if ($attribute !== null) {
			$params[] = $attribute;
			$query   .= ' AND attribute = ?';
		}

		if ($userType !== null) {
			$params[] = $userType;
			$query   .= ' AND u.type = ?';
		}

		if ($setID !== null) {
			$params[] = (int) $setID;
			$query   .= ' AND set_id = ?';
		}

		// Daten sammeln

		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$cacheKey  = sly_Cache::generateKey('userdata', $attribute, $userType, (int) $setID);
		$data      = $cache->get($namespace, $cacheKey, false);

		if (!is_array($data)) {
			$data = array();
			$sql  = WV_SQL::getInstance();

			$sql->query($query, $params, '~');

			foreach ($sql as $row) {
				$row['user_id'] = (int) $row['user_id'];
				$row['set_id']  = (int) $row['set_id'];

				$data[] = $row;
			}

			$cache->set($namespace, $cacheKey, $data);
		}

		$result = array();

		foreach ($data as $row) {
			$attribute = WV16_Users::getAttribute($row['attribute']);
			$value     = $attribute->deserialize($row['value']);

			$result[] = array(
				'user_id'   => $row['user_id'],
				'set_id'    => $row['set_id'],
				'attribute' => $row['attribute'],
				'value'     => $value,
				'raw'       => $row['value']
			);
		}

		return $result;
	}
}
