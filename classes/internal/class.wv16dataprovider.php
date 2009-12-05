<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 * 
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der 
 * beiliegenden LICENSE Datei und unter:
 * 
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz 
 */

abstract class _WV16_DataProvider {
	private static $dataCache = array();
	
	public static function prefetchData($user) {
		$userID = _WV16::getIDForUser($user, false);
		$data   = self::getDataForUser($userID);
		$ids    = array();
		
		if ($data === null) return 0;
		if (!is_array($data)) $data = array($data);
		
		foreach ($data as $date) {
			$ids[] = $date->getAttributeID();
			$key = $userID.'_'.$date->getAttributeID();
			self::$dataCache[$key]['object'] = $date;
			self::$dataCache[$key]['name']   = $date->getAttribute()->getName();
		}
		
		// Kennzeichnen, dass wir für dieses Objekt definitv alle im Moment verfügbaren Daten geholt haben.
		// Dann können andere Methoden davon ausgehen, dass es nicht mehr zu holen gibt, als hier vorliegen.
		self::$dataCache[$userID] = $ids;
		
		return count($ids);
	}
	
	/**
	 * Artikeltyp ermitteln
	 *
	 * Diese Methode gibt die ID des Typs eines Artikels zurück.
	 *
	 * @param  mixed $article  der Artikel
	 * @return int             die ID des Artikeltyps oder -1, falls der Artikel noch keinem Typ zugeordnet wurde
	 */
	public static function getUserType($user) {
		$type = WV_SQL::getInstance()->fetch('type_id', 'wv16_users', 'id = '._WV16::getIDForUser($user, false));
		return $type ? intval($type) : -1;
	}

	/**
	 * Artikeltyp als Objekt ermitteln
	 *
	 * Diese Methode gibt ein _WV2_ArticleType-Objekt zurück, anstatt nur der ID.
	 *
	 * @param  mixed $article    der Artikel
	 * @return _WV2_ArticleType  der Artikeltyp oder null, falls der Artikel noch keinem Typ zugeordnet wurde
	 */
	public static function getUserTypeAsObject($user) {
		$type = self::getUserType($user);
		return $type == -1 ? null : _WV16_UserType::getInstance($type);
	}

	/**
	 * Prüft, ob ein Benutzer von einem bestimmten Typ ist.
	 *
	 * @param  mixed $user      der Benutzer
	 * @param  mixed $userType  die ID / der Name des Benutzertyps oder null, falls alle
	 * @return bool             true oder false
	 */
	public static function isUserOfType($user, $userType) {
		return _WV16::getIDForUserType($userType, false) == self::getUserType($user);
	}

	/**
	 * Benutzertypen ermitteln
	 *
	 * Diese Methode gibt eine Liste aller Artikeltypen als _WV2_ArticleType-Objekte zurück.
	 *
	 * @param  string $sortby     das Sortierkriterium (kann jedes Attribut der Relation sein)
	 * @param  string $direction  die Sortierreihenfolge ("ASC" oder "DESC")
	 * @return array              eine Liste von _WV2_ArticleType-Objekten, die passen
	 */
	public static function getAllUserTypes($sortby = 'title', $direction = 'ASC') {
		$types = array();
		$data  = WV_SQL::getInstance()->getArray('SELECT * FROM #_wv16_utypes WHERE 1 ORDER BY '.$sortby.' '.$direction, '#_');

		foreach ($data as $id => $row) {
			$types[] = _WV16_UserType::getInstance(intval($id), array_merge(array('id' => intval($id)), $row));
		}

		return $types;
	}

	
	
	
	/**
	 * (Benötigte) Attribute ermitteln
	 *
	 * Diese Methode ermittelt für einen Benutzertyp die dazugehörigen Attribute.
	 * Wird für den Benutzertyp -1 übergeben, so werden alle vorhandenen
	 * Attribute zurückgegeben.
	 *
	 * @param  mixed $userType   die ID / der Name des Artikeltyps
	 * @return array             eine Liste von _WV16_Attribute-Objekten
	 */
	public static function getAttributesForUserType($userType) {
		if ($userType === -1) return self::getAllAttributes();
		
		$sql        = WV_SQL::getInstance();
		$return     = array();
		$userType   = _WV16::getIDForUserType($userType, false);
		$attributes = $sql->getArray('SELECT attribute_id FROM #_wv16_utype_attrib WHERE user_type = '.$userType, '#_');
		
		foreach ($attributes as $row) {
			$return[] = _WV16_Attribute::getInstance(intval($row));
		}
		
		return $return;
	}
	
	
	

	/**
	 * Benutzeranzahl ermitteln
	 *
	 * Diese Methode ermittelt, wie viele Artikel einem Artikeltyp angehören.
	 *
	 * @param  mixed $articleType  der Artikeltyp als ID oder Name
	 * @return int                 die Anzahl der zugehörigen Artikel
	 */
	public static function getUserCountByType($userType) {
		return WV_SQL::getInstance()->count('wv16_users', 'type_id = '._WV16::getIDForUserType($userType, false));
	}
	
	
	

	/**
	 * Benutzerliste ermitteln
	 *
	 * Diese Methode erzeugt eine Liste von OOArticle-Objekten basierend auf den
	 * gegebenen Filterkriterien zurück.
	 * 
	 * Für das Sortierkriterium ($sortby) stehen die Tabellen-Aliase
	 * 
	 *  - at (article_type)
	 *  - a (rex_article)
	 * 
	 * bereit.
	 *
	 * @param  mixed  $userType     der Benutzertyp als ID oder Name
	 * @param  string $sortby       das Sortierkriterium (aus der Relation article oder wv2_article_type)
	 * @param  string $direction    die Sortierrichtung
	 * @param  string $limitClause  eine optionale "LIMIT a,b"-Angabe
	 * @return array                Liste von passenden OOArticle-Objekten
	 */
	public static function getUsersByType($userType, $sortby = 'login', $direction = 'ASC', $limitClause = '') {
		$userType = _WV16::getIDForUserType($userType, false);
		$return   = array();
		$sql      = WV_SQL::getInstance();

		$sql->query(
			'SELECT id FROM #_wv16_users at, #_article a WHERE type_id = '.$userType.' '.
			'ORDER BY '.$sortby.' '.$direction.' '.$limitClause, '#_');

		foreach ($sql as $row) {
			$return[] = _WV16_User::getInstance(intval($row['id']));
		}

		return $return;
	}
	
	
	

	/**
	 * Alle verfügbaren Attribute holen
	 *
	 * Diese Methode gibt alle vorhandenen Metainfos (nicht die Metadaten!)
	 * zurück. Dabei kann ein WHERE-Statement für die wv2_metainfo-Relation
	 * sowie Sortierkriterium und -richtung angegeben werden.
	 *
	 * @param  string $where      das WHERE-Kriterium
	 * @param  string $sortby     das Sortierkriterium (kann jedes Attribut der Relation sein)
	 * @param  string $direction  die Sortierreihenfolge ("ASC" oder "DESC")
	 * @return array              eine Liste von _WV16_Attribute-Objekten, die passen
	 */
	public static function getAllAttributes($where = '1', $sortby = 'position', $direction = 'ASC') {
		$attr = array();
		$data  = WV_SQL::getInstance()->getArray(
			'SELECT * FROM '.WV_SQL::getPrefix().'wv16_attributes '.
			'WHERE '.$where.' ORDER BY '.$sortby.' '.$direction);

		foreach ($data as $id => $row) {
			$id     = intval($id);
			$attr[] = _WV16_Attribute::getInstance($id, array_merge(array('id'=>$id), $row));
		}

		return $attr;
	}
	
	
	

	/**
	 * Metadaten für ein einzelnes Objekt ermitteln
	 *
	 * Diese Methode ermittelt für ein bestimmtes Objekt die angelegten
	 * Metadaten. Zurückgegeben wird ein Array aus _WV2_MetaData-Objekten. Gibt
	 * es nur ein Metadatum, wird dieses direkt (ohne Array) zurückgegeben. Gibt
	 * es kein Metadatum, so wird ein MetaData-Objekt mit dem Standardwert
	 * zurückgegeben. Ist der Standardwert null, wird in diesem Fall direkt null
	 * zurückgegeben.
	 *
	 * Wenn ein Array zurückgegeben wird, so ist es assoziativ mit den
	 * Metainfo-Namen als Schlüsseln.
	 *
	 * Wenn als als Standardwert etwas anderes als null angegeben wird und der
	 * Anwender sicher ist, dass es nur ein Metadatum geben kann (weil er z.B.
	 * $metainfoToken angegeben hat), kann man immer direkt getValue() bzw.
	 * getKey() auf den Rückgabewert dieser Methode anwenden.
	 *
	 * @param  mixed  $user       der Benutzer
	 * @param  mixed  $attribute  der Name der Metainformation oder null für alle
	 * @param  mixed  $default    der Standardwert, falls kein Metadatum gefunden wurde
	 * @return array              Liste der Metdaten wie oben beschrieben
	 */
	public static function getDataForUser($user, $attribute = null, $default = null) {
		$sql       = WV_SQL::getInstance();
		$userID    = _WV16::getIDForUser($user, false);
		$attribute = _WV16::getIDForAttribute($attribute, true);
		$return    = array();
		
		// Wurden alle Attribute angefragt? Haben wir bereits alle Daten für diesen Benutzer?
		if ($attribute === null && isset(self::$dataCache[$userID])) {
			$attributes = self::$dataCache[$userID];
			
			// Hat dieser Benutzer keine Attribute?
			if (empty($attributes)) {
				return is_null($default) ? null : new _WV16_UserValue($default, null, null);
			}
			
			// Bei genau einem Attribut geben wir dieses direkt zurück.
			if (count($attributes) == 1) {
				$id = reset($attributes);
				return self::$dataCache[$userID.'_'.$id]['object'];
			}
			
			// Der Benutzer hat mehr als ein Attribut.
			$values = array();
			foreach ($attributes as $id) $values[self::$dataCache[$userID.'_'.$id]['name']] = self::$dataCache[$userID.'_'.$id]['object'];
			return $values;
		}
		// Wurde ein bestimmtes Attribut angefragt?
		elseif ($attribute !== null) {
			// Attribut bereits geholt. Cool!
			if (isset(self::$dataCache[$userID.'_'.$attribute])) return self::$dataCache[$userID.'_'.$attribute]['object'];
			
			// Wert existiert nicht. Aber vielleicht haben wir schon alle Daten geholt und die Anfrage
			// des Anwenders zielt auf eine eh nicht vorhandene? Dann können wir direkt den Standardwert
			// zurückgeben, da wir wissen, dass der Wert nicht existieren kann.
			if (isset(self::$dataCache[$userID])) {
				return is_null($default) ? null : new _WV16_UserValue($default, null, null);
			}
		}
		
		// Cache-Miss. Mist. Dann eben in die Datenbank...

		$ids   = array();
		$query =
			'SELECT name, attribute_id, value FROM '.
			'#_wv16_user_values, #_wv16_attributes WHERE '.
			'user_id = '.$userID.' '.
			'AND attribute_id = id '.
			($attribute !== null ? 'AND attribute_id = '.$attribute.' ' : '');

		foreach ($sql->iquery($query, '#_') as $row) {
			$sql->in();
			
			// Daten holen
			$return[$row['name']] = new _WV16_UserValue($row['value'], intval($row['attribute_id']), $userID);
			
			// Daten cachen
			$key = $userID.'_'.intval($row['attribute_id']);
			self::$dataCache[$key]['object'] = $return[$row['name']];
			self::$dataCache[$key]['name']   = $row['name'];
			$ids[] = intval($row['attribute_id']);
			
			$sql->out();
		}
		
		// Wenn wir alle Metadaten geholt haben, merken wir uns eine Liste der IDs.
		if ($attribute === null) {
			self::$dataCache[$userID] = $ids;
		}
		
		// Nichts gefunden? Dann Standardwert oder null.
		if (empty($return)) {
			return is_null($default) ? null : new _WV16_UserValue($default, null, null);
		}

		// dearrayfizieren
		return count($return) == 1 ? reset($return) : $return;
	}

	// Shortcut
	public static function userData($user, $attribute = null, $default = null) {
		return self::getDataForUser($user, $attribute, $default);
	}
	
	

	
	/**
	 * Passende Objekte anhand ihrer Metadaten ermitteln
	 *
	 * Gibt ein Array aus OOArticle/OOCategory/OOMedia-Objekten zurück, die
	 * einen bestimmten Typ und eine bestimmte MetaInfo haben. Zusätzlich kann
	 * angegeben werden, ob die Metainfo einen bestimmten Wert hat ($value) und
	 * wie dieser Wert zu verstehen ist (ob er nur enthalten sein muss oder ob
	 * der Wert exakt dem Suchbegriff entsprechen muss).
	 *
	 * Die nötigen Konstanten für den $operator-Parameter sind in der Klasse WV2
	 * definiert. Nicht jeder Datentyp unterstützt jeden Filterparameter.
	 *
	 * Es wird empfohlen, direkt die Methoden getXXXWithMetaData zu verwenden, da
	 * diese auch nur die benötigten / erlaubten Parameter enthalten.
	 * 
	 * Als Sortierkriterium muss eine Angabe der Form "tabelle.spalte" angegeben
	 * werden. Mögliche Tabellen sind
	 * 
	 *  - user_values
	 *  - attributes
	 *  - users
	 * 
	 * Wird eine nicht bekannte Tabelle angegeben, wird die Angabe einfach
	 * ignoriert.
	 *
	 * @param  mixed   $metainfo     der Name der Metainformation
	 * @param  mixed   $articleType  die ID / der Name des Artikeltyps oder null, falls alle
	 * @param  string  $value        der gesuchte Metdaten-Wert oder null, falls egal
	 * @param  int     $operator     Anweisungen an den Datentyp, wie die Suche nach dem Wert ($value) zu erfolgen hat
	 * @param  string  $sort         ine optionale "ORDER BY"-Klausel (ohne "ORDER BY")
	 * @param  int     $clang        die Sprache der Artikel / Kategorien / Medien (WV2::CLANG-Konstanten)
	 * @param  int     $type         der Typ (WV2::TYPE-Konstanten)
	 * @param  boolean $onlineOnly   wenn true, werden nur online Artikel berücksichtigt
	 * @return array                 eine Liste von passenden Artikeln / Kategorien / Medien
	 */
	public static function getUsersWithAttribute($attribute, $userType = null, $value = null, $operator = null, $sort = null) {
		$attribute = _WV16::getIDForAttribute($metainfo, false);
		$userType  = _WV16::getIDForUserType($userType, true);
		
		//////////////////////////////////////////////////////////////////////////
		// Objekte finden, die die gesuchte Metainformation besitzen
		// Ob der gesuchte Wert enthalten ist, prüfen später die Datentypen
		// selbstständig.
		
		$sortTable  = strpos($sort, '.') === false ? ''    : substr($sort, 0, strpos($sort,'.'));
		$sortColumn = strpos($sort, '.') === false ? $sort : substr($sort, strpos($sort,'.') + 1);

		switch ( $sortTable ) {
			case 'user_values': $sortTable = 'uv';   break;
			case 'attributes':  $sortTable = 'attr'; break;
			case 'users':       $sortTable = 'u';    break;
			default:            $sortTable = '';
		}
		
		$query =
			'SELECT uv.* '.
			'FROM      #_wv16_user_values uv '.
			'LEFT JOIN #_wv16_attributes  attr ON uv.attribute_id = attr.id '.
			'LEFT JOIN #_wv16_users       u    ON u.id = uv.user_id '.
			'WHERE a.id = '.$attribute.
			($userType != null ? ' AND type_id = '.$userType : '').
			($sortTable        ? ' ORDER BY '.$sortTable.'.'.$sortColumn : '');

		$sql    = WV_SQL::getInstance();
		$return = array();

		$sql->query($query, '#_');

		// Nichts gefunden? Und tschüss!

		if ($sql->rows() == 0) return array();

		// Datentyp ermitteln

		$datatype = _WV16_Attribute::getDatatypeWithParams($attribute);
		$params   = $datatype['params'];

		// Gefundene Daten durchgehen

		foreach ($sql as $row) {
			$sql->in();
			
			if ($value === null) {
				$return[] = _WV16_User::getInstance(intval($row['user_id']));
			}
			else {
				$contained = _WV2::callForDatatype(
					intval($datatype['datatype']),
					'isValueContained',
					array($value, $row['value'], $params, $operator));

				if ($contained) {
					$return[] = _WV16_User::getInstance(intval($row['user_id']));
				}
			}

			$sql->out();
		}

		return $return;
	}
	
	
	

	/**
	 * Werte eines Attributs erfahren
	 *
	 * Diese Methode ermittelt alle möglichen Werte, die eine Metainfo annehmen
	 * kann bzw. angenommen hat. Bei Strings macht nur die Suche nach
	 * angenommenen Werten Sinn, bei SELECTs auch die Suche nach den möglichen
	 * Werten.
	 *
	 * @param  mixed  $metainfo         die Metainfo
	 * @param  bool   $getOnlyExisting  wenn true, werden nur die Werte zurückgegeben, die eine Metainfo auch wirklich angenommen hat
	 * @param  int    $type             der Typ (WV2::TYPE-Konstanten)
	 * @return array                    eine Liste von Alternativen
	 */
	public static function getAttributeValueSet($attribute, $getOnlyExisting = false) {
		$attribute = _WV16::getIDForAttribute($attribute, false);
		$data      = _WV16_Attribute::getDatatypeWithParams($attribute);
		if (!$data) return array();

		// Da PHP keine Arrays zulässt, bei denen die Keys zwar
		// Strings, aber Zahlen sind ("8" wird immer zu 8 konvertiert),
		// muss der Datentyp explizit angeben, ob seine Liste assoziativ
		// oder normal zu behandeln ist.

		$datalist = array();
		$isAssoc  = _WV2::callForDatatype(intval($data['datatype']), 'usesAssociativeResults');

		if ($getOnlyExisting) {
			$data = self::getUserDataForToken($attribute, null);

			foreach ($data as $d) {
				$datalist = $isAssoc ? self::merge($datalist, $d->getValue()) : ($datalist + $d->getValue());
			}

			$datalist = array_unique($datalist);
		}
		else {
			$datalist = _WV2::callForDatatype(intval($data['datatype']), 'extractValuesFromParams', $data['params']);
		}

		return $datalist;
	}



	
	/**
	 * Prüfen, ob Wert vorhanden ist
	 * 
	 * Diese Methode prüft, ob ein bstimmtes Objekt ein bestimmtes Metadatum
	 * besitzt.
	 * 
	 * @param  mixed  $object       das Objekt
	 * @param  mixed  $article      der Artikel
	 * @param  mixed  $category     die Kategorie
	 * @param  mixed  $medium       das Medium
	 * @param  mixed  $metainfo     die Metainfo
	 * @param  mixed  $value        der gesuchte Wert
	 * @param  int    $clang        die Sprache des Objekts
	 * @param  int    $type         der Typ des Objekts
	 * @return boolean              true, wenn der Artikel die gesuchte Information bestitzt, sonst false
	 */
	public static function hasUserValue($user, $attribute, $value) {
		$data = self::getDataForUser($user, $attribute, null);
		if (!$data) return false;

		$v = $metadata->getValue();

		if (!is_array($v)) return $value == $v;
		return in_array($value, array_keys($v)) || in_array($value, array_values($v));
	}

	
	
	
	/**
	 * Attribute für alle Benutzer ermitteln
	 *
	 * Diese Methode liefert NICHT die Metadaten eines einzelnen Artikels zurück,
	 * sondern die Metadaten aller Artikel, die die gewählte Metainformation
	 * besitzen. Daher kann man ihr auch nicht die ID eines Artikels übergeben.
	 * Sie dient primär als Hilfsmethode für getXXXValueSet().
	 *
	 * @param  mixed      $metainfo       die Metainfo
	 * @param  int|string $articleType    die ID / der Name des gesuchten Artikeltyps oder null für keine Angabe
	 * @param  int        $clang          die gewünschte Sprache
	 * @param  int        $type           der Typ des Objekts
	 * @return array                      Liste von _WV2_MetaData-Objekten
	 */
	public static function getUserDataForToken($attribute = null, $userType = null) {
		$attribute = _WV16::getIDForAttribute($attribute, true);
		$userType  = _WV16::getIDForUserType($userType, true);
		if ($attribute) $params['id']     = $attribute;
		if ($userType)  $params['typeID'] = $userType;
		return self::getUserData($params);
	}


	

	/**
	 * Attribute ermitteln
	 *
	 * Gibt eine Liste von Metadaten zurück. Sie dient als Basismethode zur
	 * Abfrage von Metdaten von Artikeln und bietet verschiedene Parameter zur
	 * Selektion, Gruppierung oder Sortierung. Die anderen MetaData-Methoden
	 * sind quasi Shortcuts für häufig benutzte "Queries".
	 *
	 * Im $params-Array werden folgende Elemente in der folgenden Reihenfolge
	 * abgearbeitet:
	 *
	 * - 'userID' => int|array
	 *   
	 * - 'name' => string|array
	 *   
	 * - 'id' => int|array
	 *   
	 * - 'typeID' => int|string|array
	 *   wenn gesetzt, werden die ermittelten Artikel nochmals nach ihrem Typ / ihren Typen gefiltert
	 * - 'orderby' => string
	 *   kann jedes Attribut aus den Relationen wv18_users (u), wv16_user_values (uv) und wv16_attributes (attr) sein
	 *   default ist "id"
	 * - 'direction' => "ASC"|"DESC"
	 *   die Sortierreihenfolge
	 *   default ist "ASC"
	 * 
	 * Die Parameter in $params können auch als ein JSON-kodierter String
	 * angegeben werden ("{articleID:5}").
	 *
	 * Als Rückgabe generiert die Methode ein Array aus _WV2_MetaData-Objekten.
	 *
	 * @param  array $params  die Suchparameter
	 * @return array          die Liste der passenden Metadaten
	 */
	public static function getUserData($params = null) {
		if (!is_array($params)) $params = json_decode($params, true);
		
		$query =str_replace('#_', WV_SQL::getPrefix(), 
			'SELECT uv.* '.
			'FROM      #_wv16_user_values uv '.
			'LEFT JOIN #_wv16_attributes  attr ON uv.attribute_id = attr '.
			'LEFT JOIN #_wv16_users       u    ON uv.user_id      = u.id '.
			'WHERE %%%name%%% %%%user%%% %%%type%%% 1');

		// Parameter auspacken. extract() wäre uns zu unsicher, daher lieber
		// Stück für Stück von Hand.

		$userID = self::make_array(isset($params['userID']) ? $params['userID'] : null);
		$typeID = self::make_array(isset($params['typeID']) ? $params['typeID'] : null);
		$name   = self::make_array(isset($params['name'])   ? $params['name']   : null);
		$id     = self::make_array(isset($params['id'])     ? $params['id']     : null);

		$typeFunction = 'intval';

		if (self::array_any('is_string', $typeID)) {
			$typeFunction = 'mysql_real_escape_string';
		}

		// Parameter vorbereiten und entschärfen

		$userID = array_map('intval', $userID);
		$typeID = array_map($typeFunction, $typeID);
		$name   = array_map('mysql_real_escape_string', $name);
		$id     = array_map('intval', $id);

		// Minimieren

		$userID = array_unique($userID);
		$typeID = array_unique($typeID);
		$name   = array_unique($name);
		$id     = array_unique($id);

		// In Query einsetzen

		$query = str_replace('%%%user%%%',  count($userID) ? 'user_id IN ('.implode(',', $userID).') AND'     : '', $query);
		$query = str_replace('%%%type%%%',  count($typeID) ? 'type_id IN ('.implode(',', $typeID).') AND'     : '', $query);
		$query = str_replace('%%%name%%%',  count($name)   ? 'attr.name IN ("'.implode('","', $name).'") AND' : '', $query);
		$query = str_replace('%%%name%%%',  count($id)     ? 'attr.id IN ('.implode(',', $id).') AND'         : '', $query);

		// Query ist (fast) fertig.

		$orderby   = isset($params['orderby'])   ? $params['orderby']   : 'u.id';
		$direction = isset($params['direction']) ? $params['direction'] : 'ASC';
		$query    .= ' ORDER BY '.$orderby.' '.$direction;
		$result    = array();

		foreach ($sql->iquery($query) as $row) {
			$sql->in();
			$result[] = new _WV16_UserValue($row['value'], intval($row['attribute_id']), intval($row['user_id']));
			$sql->out();
		}

		return $result;
	}

	
	

	/**
	 * Schlüsselbasiertes Mergen
	 *
	 * Merged zwei Arrays anhand ihrer Schlüssel.
	 * Gibt es hierfür eine PHP-interne Alternative?
	 *
	 * @param  array $array1  das erste Array
	 * @param  array $array2  das zweite Array
	 * @return array          das Array mit den Werten aus beiden Arrays
	 */
	private static function merge($array1, $array2) {
		$result = $array1;
		foreach ( $array2 as $key => $value ) {
			if ( !in_array($key, array_keys($result),true) ) $result[$key] = $value;
		}
		return $result;
	}

	/**
	 * Hilfsfunktion: Ersetzen von Werten in Array
	 *
	 * Sucht in einem Array nach Elementen und ersetzt jedes Vorkommen durch
	 * einen neuen Wert. Soll in PHP 5.3 mit in die Standardfunktionen
	 * aufgenommen werden.
	 *
	 * @param  array $array        das Such-Array
	 * @param  mixed $needle       der zu suchende Wert
	 * @param  mixed $replacement  der Ersetzungswert
	 * @return array               das resultierende Array
	 */
	private static function array_replace($array, $needle, $replacement) {
		$i = array_search($needle, $array);
		if ($i === false) return $array;
		$array[$i] = $replacement;
		return self::array_replace($array, $needle, $replacement);
	}

	/**
	 * Hilfsfunktion: Löschen von Werten aus einem Array
	 *
	 * Sucht in einem Array nach Elementen und löscht jedes Vorkommen.
	 *
	 * @param  array $array   das Such-Array
	 * @param  mixed $needle  der zu suchende Wert
	 * @return array          das resultierende Array
	 */
	private static function array_delete($array, $needle) {
		$i = array_search($needle, $array);
		if ($i === false) return $array;
		unset($array[$i]);
		return self::array_delete($array, $needle);
	}

	/**
	 * Hilfsfunktion: Anwenden eines Prädikats auf ein Array
	 *
	 * Gibt true zurück, wenn das Prädikat auf mindestens ein Element des Arrays
	 * zutrifft.
	 *
	 * @param  string $predicate  das Prädikat (Funktionsname als String)
	 * @param  array  $array      das Such-Array
	 * @return bool               true, wenn das Prädikat mindestens 1x zutrifft
	 */
	private static function array_any($predicate, $array) {
		foreach ( $array as $element ) if ( $predicate($element) ) return true;
		return false;
	}

	/**
	 * Hilfsfunktion: Anwenden eines Prädikats auf ein Array
	 *
	 * Gibt true zurück, wenn das Prädikat auf mindestens einen Schlüssel des
	 * Arrays zutrifft.
	 *
	 * @param  string $predicate  das Prädikat (Funktionsname als String)
	 * @param  array  $array      das Such-Array
	 * @return bool               true, wenn das Prädikat mindestens 1x zutrifft
	 */
	private static function array_any_key($predicate, $array) {
		return self::array_any($predicate, array_keys($array));
	}

	/**
	 * Arrayfizieren
	 * 
	 * Macht aus einem Skalar ein Array
	 *
	 * @param  $element  das Element
	 * @return array     leeres Array für $element=null, einelementiges Array für $element=Skalar, sonst direkt $element
	 */
	private static function make_array($element) {
		if ( $element === null  ) return array();
		if ( is_array($element) ) return $element;
		return array($element);
	}
}
