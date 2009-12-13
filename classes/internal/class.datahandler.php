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

/**
 * Daten-Verarbeitungs-Helfer
 *
 * Diese Klasse stellt dem AddOn eine Reihe von Methoden bereit, um mit den
 * Benutzerdaten zu interagieren. Von außen sollten nur die in WV16_Users
 * verfügbaren Methoden genutzt werden, da diese Klasse intern ist.
 *
 * @ingroup internal
 * @author  Christoph, Zozi
 * @since   1.0
 */
abstract class _WV16_DataHandler
{
	private static $dataCache = array();
	
	/**
	 * Daten prefetchen
	 * 
	 * Diese Methode wird für einen bestimmten Benutzer sämtliche Attributwerte aus dem gerade
	 * aktuellen ValueSet abrufen und im Cache zwischenspeichern. Um danach kenntlich zu machen,
	 * dass wirklich alle verfügbaren Daten geholt werden, wird sich die API merken, dass alle
	 * Daten geholt wurden.
	 * 
	 * Eine Abfrage kann dann zuerst prüfen, ob ein bestimmter Wert bereits
	 * geholt wurde. Falls nicht, kann sie prüfen, ob für diesen Benutzer und das aktuelle Set
	 * bereits alle Daten geholt werden. Ist dies der Fall, muss nicht von einem Cache-Miss
	 * ausgegangen werden, sondern von einer Abfrage auf ein nicht existentes Attribut. Dann
	 * muss die Abfrage gar nicht erst in die Datenbank laufen, um dort festzustellen, dass es
	 * das Attribut nicht gibt.
	 * 
	 * @param  mxied $user  der Benutzer (_WV16_User oder seine ID (int))
	 * @return int          die Anzahl der gecachten Attribute
	 */
	private static function prefetchData($user)
	{
		$userID = _WV16::getIDForUser($user, false);
		$setID  = self::getFirstSetID($user);
		$data   = self::getDataForUser($userID);
		$ids    = array();
		
		if ($data === null) return 0;
		if (!is_array($data)) $data = array($data);
		
		$ids = array();
		
		foreach ($data as $date) {
			self::cacheData($date, $userID, $setID);
			$ids[] = $date->getAttributeID();
		}
		
		// Kennzeichnen, dass wir für dieses Objekt definitv alle im Moment verfügbaren Daten geholt haben.
		// Dann können andere Methoden davon ausgehen, dass es nicht mehr zu holen gibt, als hier vorliegen.
		self::$dataCache[$userID.'_'.$setID] = $ids;
		
		return count(self::$dataCache[$userID.'_'.$setID]);
	}
	
	/**
	 * Einzelnen Attributwert cachen
	 * 
	 * Hier wird ein einzelner Attributwert für einen bestimmten Benutzer gecached.
	 * 
	 * Achtung: Hier darf nicht die Stelle [userID_setID] auf true gesetzt werden, da durch das Cachen
	 * eines einzelnen Wertes noch nicht sichergestellt ist, dass wirklich *alle* Werte aus der Datenbank
	 * geholt wurden!
	 * 
	 * @param   _WV16_UserValue $value   der Benutzerwert
	 * @param  int              $userID  die Benutzer-ID
	 * @param  int              $setID   die ValueSet-ID des Benutzers
	 */
	private static function cacheData($value, $userID, $setID)
	{
		$key = $userID.'_'.$setID.'_'.$value->getAttributeID();
		self::$dataCache[$key]['object'] = $value;
		self::$dataCache[$key]['name']   = $value->getAttributeName();
	}
	
	public static function setDataForUser($user, $attribute, $value, $useTransaction = true)
	{
		// Wenn das aktuelle Objekt auf read-only Daten operiert (z.B. weil varisale
		// die Daten persistent gespeichert hat), darf man den Wert eines Attributs
		// nicht mehr ändern. Bei gelöschten Benutzern darf man ebenfalls keine
		// Werte mehr ändern.
		
		if ($user->isDeleted() || WV16_Users::isReadOnlySet($user->getSetID())) {
			return false;
		}
		
		// Daten vorbereiten
		
		$value      = strval($value);
		$attribute  = _WV16::getIDForAttribute($attribute);
		$attributes = WV16_Users::getAttributesForUserType($user->getTypeID(), true);
		
		// Prüfen, ob das Attribut überhaupt zu dem aktuellen Benutzertyp gehört.
		// Dazu reicht es, die Liste der geholten Attribute durchzugehen, da ein
		// Benutzer immer alle Attribute hat, die zum Typ gehören (auch wenn sie
		// mit ihrem jeweiligen Standardwert belegt sind).
		
		if (!in_array($attribute, $attributes)) { // getValues() holt die Attribute, falls nötig!
			return false;
		}
		
		// OK, das Attribut darf gesetzt werden. :-)
		
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			$sql->queryEx(
				'UPDATE #_wv16_user_values SET value = ? WHERE user_id = ? AND set_id = ? AND attribute_id = ?',
				array($value, $user->getID(), $user->getSetID(), $attribute), '#_'
			);

			// nun noch den Cache aktualisieren
			
			$date = new _WV16_UserValue($value, $attribute, $user);
			self::cacheData($date, $user->getID(), $user->getSetID());
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	/**
	 * @todo  Das Ergebnis dieser Methode sollte gecached werden.
	 */
	public static function getFirstSetID($user)
	{
		$userID = _WV16::getIDForUser($user, false);
		$sql    = WV_SQLEx::getInstance();
		$id     = $sql->saveFetch('MIN(set_id)', 'wv16_user_values', 'user_id = ? AND set_id >= 0', $userID);
		
		// Die kleinste erlaubte ID ist 1. Wenn noch keine Werte vorhanden sein
		// sollten, müssen wir dies hier dennoch sicherstellen.
		
		return $id == 0 ? 1 : (int) $id;
	}
	
	/**
	 * Artikeltyp ermitteln
	 *
	 * Diese Methode gibt die ID des Typs eines Artikels zurück.
	 *
	 * @todo  Das Ergebnis dieser Methode sollte gecached werden.
	 *
	 * @param  mixed $article  der Artikel
	 * @return int             die ID des Artikeltyps oder -1, falls der Artikel noch keinem Typ zugeordnet wurde
	 */
	public static function getUserType($user)
	{
		$userID = _WV16::getIDForUser($user, false);
		$sql    = WV_SQLEx::getInstance();
		$type   = $sql->saveFetch('type_id', 'wv16_users', 'id = ?', $userID);
		
		return $type ? (int) $type : -1;
	}

	/**
	 * Artikeltyp als Objekt ermitteln
	 *
	 * Diese Methode gibt ein _WV2_ArticleType-Objekt zurück, anstatt nur der ID.
	 *
	 * @param  mixed $article    der Artikel
	 * @return _WV2_ArticleType  der Artikeltyp oder null, falls der Artikel noch keinem Typ zugeordnet wurde
	 */
	public static function getUserTypeAsObject($user)
	{
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
	public static function isUserOfType($user, $userType)
	{
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
	public static function getAllUserTypes($sortby = 'title', $direction = 'ASC')
	{
		$types = array();
		$sql   = WV_SQLEx::getInstance();
		$mode  = WV_SQLEx::RETURN_FALSE;
		$data  = $sql->getArray('SELECT * FROM #_wv16_utypes WHERE 1 ORDER BY '.$sortby.' '.$direction, array(), '#_', $mode);

		foreach ($data as $id => $row) {
			$types[] = _WV16_UserType::getInstance($id, array_merge(array('id' => $id), $row));
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
	public static function getAttributesForUserType($userType, $returnAsIDs = false)
	{
		if ($userType === -1) {
			return self::getAllAttributes();
		}
		
		$sql      = WV_SQLEx::getInstance();
		$mode     = WV_SQLEx::RETURN_FALSE;
		$return   = array();
		$userType = _WV16::getIDForUserType($userType, false);
		
		// In utype_attrib stehen immer nur Live-Attribute (deleted=0), daher ist kein JOIN notwendig, um nur
		// die Live-Attribute zu selektieren.
		
		$attributes = $sql->getArray('SELECT attribute_id FROM #_wv16_utype_attrib WHERE user_type = ?', $userType, '#_', $mode);
		
		foreach ($attributes as $id) {
			$return[] = $returnAsIDs ? $id : _WV16_Attribute::getInstance($id);
		}
		
		return $return;
	}
	
	/**
	 * Attribut ermitteln
	 *
	 * Diese Methode holt für ein Attribut das Objekt.
	 *
	 * @param  mixed $userType   die ID / der Name des Artikeltyps
	 * @return array             eine Liste von _WV16_Attribute-Objekten
	 */
	public static function getAttribute($attribute)
	{
		return _WV16_Attribute::getInstance($attribute);
	}

	/**
	 * Benutzeranzahl ermitteln
	 *
	 * Diese Methode ermittelt, wie viele Artikel einem Artikeltyp angehören.
	 *
	 * @param  mixed $articleType  der Artikeltyp als ID oder Name
	 * @return int                 die Anzahl der zugehörigen Artikel
	 */
	public static function getUserCountByType($userType)
	{
		return WV_SQLEx::getInstance()->count('wv16_users', 'type_id = ?', _WV16::getIDForUserType($userType, false));
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
	public static function getUsersByType($userType, $sortby = 'login', $direction = 'ASC', $limitClause = '')
	{
		$userType = _WV16::getIDForUserType($userType, false);
		$return   = array();
		$sql      = WV_SQLEx::getInstance();

		$sql->queryEx(
			'SELECT id FROM #_wv16_users at, #_article a WHERE type_id = ? '.
			'ORDER BY '.$sortby.' '.$direction.' '.$limitClause,
			$userType, '#_'
		);

		foreach ($sql as $row) {
			$return[] = _WV16_User::getInstance($row['id']);
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
	public static function getAllAttributes($where = '1', $sortby = 'position', $direction = 'ASC')
	{
		if (empty($where)) $where = 'deleted = 0';
		else $where .= ' AND deleted = 0';
		
		$attr = array();
		$sql  = WV_SQLEx::getInstance();
		$mode = WV_SQLEx::RETURN_FALSE;
		$data = $sql->getArray(
			'SELECT * FROM #_wv16_attributes WHERE '.$where.' ORDER BY '.$sortby.' '.$direction,
			array(), '#_', $mode
		);

		foreach ($data as $id => $row) {
			$attr[] = _WV16_Attribute::getInstance($id, array_merge(array('id' => $id), $row));
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
	public static function getDataForUser($user, $attribute = null, $default = null, $setID = null)
	{
		$userID    = _WV16::getIDForUser($user, false);
		$attribute = _WV16::getIDForAttribute($attribute, true);
		$setID     = $setID === null ? self::getFirstSetID($user) : (int) $setID;
		$key       = $userID.'_'.$setID;
		
		// Wurden alle Attribute angefragt? Haben wir bereits alle Daten für diesen Benutzer?
		
		if ($attribute === null && isset(self::$dataCache[$key])) {
			$attributes = self::$dataCache[$key];
			
			// Hat dieser Benutzer keine Attribute?
			
			if (empty($attributes)) {
				return is_null($default) ? null : new _WV16_UserValue($default, null, null, null);
			}
			
			// Bei genau einem Attribut geben wir dieses direkt zurück.
			
			if (count($attributes) == 1) {
				$id = reset($attributes);
				return self::$dataCache[$key.'_'.$id]['object'];
			}
			
			// Der Benutzer hat mehr als ein Attribut.
			
			$values = array();
			
			foreach ($attributes as $id) {
				$values[self::$dataCache[$key.'_'.$id]['name']] = self::$dataCache[$key.'_'.$id]['object'];
			}
			
			return $values;
		}
		
		// Wurde ein bestimmtes Attribut angefragt?
		
		elseif ($attribute !== null) {
			// Attribut bereits geholt. Cool!
			
			if (isset(self::$dataCache[$key.'_'.$attribute])){
				return self::$dataCache[$key.'_'.$attribute]['object'];
			}
			
			// Wert existiert nicht. Aber vielleicht haben wir schon alle Daten geholt und die Anfrage
			// des Anwenders zielt auf eine eh nicht vorhandene? Dann können wir direkt den Standardwert
			// zurückgeben, da wir wissen, dass der Wert nicht existieren kann.
			
			if (isset(self::$dataCache[$key])) {
				return is_null($default) ? null : new _WV16_UserValue($default, null, null, null);
			}
		}
		
		// Cache-Miss. Mist. Dann eben in die Datenbank...

		$sql    = WV_SQLEx::getClone();
		$return = array();
		$ids    = array();
		$params = array($userID, $setID);
		$query  =
			'SELECT name, attribute_id, value FROM #_wv16_user_values, #_wv16_attributes '.
			'WHERE user_id = ? AND set_id = ? AND attribute_id = id';
		
		if ($attribute !== null) {
			$query   .= ' AND attribute_id = ?';
			$params[] = $attribute;
		}
		
		$sql->queryEx($query, $params, '#_', false, WV_SQLEx::RETURN_FALSE);

		foreach ($sql as $row) {
			// Daten holen
			
			$return[$row['name']] = new _WV16_UserValue($row['value'], $row['attribute_id'], $userID, $setID);
			
			// Daten cachen
			
			self::cacheData($return[$row['name']], $userID, $setID);
			$ids[] = $row['attribute_id'];
		}
		
		// Mach's gut, Dolly!
		
		$sql = null;
		unset($sql);
		
		// Wenn wir alle Metadaten geholt haben, merken wir uns das.
		
		if ($attribute === null) {
			self::$dataCache[$key] = $ids;
		}
		
		// Nichts gefunden? Dann Standardwert oder null.
		
		if (empty($return)) {
			return is_null($default) ? null : new _WV16_UserValue($default, null, null);
		}

		// dearrayfizieren
		
		return count($return) == 1 ? reset($return) : $return;
	}

	// Shortcut
	public static function userData($user, $attribute = null, $default = null, $setID = null)
	{
		$setID = $setID === null ? $user->getSetID() : $setID;
		return self::getDataForUser($user, $attribute, $default, $setID);
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
	public static function getUsersWithAttribute($attribute, $userType = null, $value = null, $operator = null, $sort = null)
	{
		$attribute = _WV16::getIDForAttribute($attribute, false);
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
			'FROM #_wv16_user_values uv '.
			'LEFT JOIN #_wv16_attributes attr ON uv.attribute_id = attr.id '.
			'LEFT JOIN #_wv16_users u ON u.id = uv.user_id '.
			'WHERE a.id = ?';

		$sql    = WV_SQLEx::getClone();
		$return = array();
		$params = array($attribute);
		
		if ($userType !== null) {
			$query   .= ' AND type_id = ?';
			$params[] = $userType;
		}
		
		if ($sortTable) {
			$query .= ' ORDER BY '.$sortTable.'.'.$sortColumn;
		}

		$sql->queryEx($query, $params, '#_', WV_SQLEx::RETURN_FALSE);

		// Nichts gefunden? Und tschüss!

		if ($sql->rows() == 0) {
			$sql = null;
			unset($sql);
			return array();
		}

		// Datentyp ermitteln

		$datatype = _WV16_Attribute::getDatatypeWithParams($attribute);
		$params   = $datatype['params'];

		// Gefundene Daten durchgehen

		foreach ($sql as $row) {
			if ($value === null) {
				$return[] = _WV16_User::getInstance($row['user_id']);
			}
			else {
				$contained = WV_Datatype::call($datatype['datatype'], 'isValueContained', array($value, $row['value'], $params, $operator));

				if ($contained) {
					$return[] = _WV16_User::getInstance($row['user_id']);
				}
			}
		}
		
		// Mach's gut, Dolly!
		
		$sql = null;
		unset($sql);

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
	public static function getAttributeValueSet($attribute, $getOnlyExisting = false)
	{
		$attribute = _WV16::getIDForAttribute($attribute, false);
		$data      = _WV16_Attribute::getDatatypeWithParams($attribute);
		
		if (!$data) {
			return array();
		}

		// Da PHP keine Arrays zulässt, bei denen die Keys zwar
		// Strings, aber Zahlen sind ("8" wird immer zu 8 konvertiert),
		// muss der Datentyp explizit angeben, ob seine Liste assoziativ
		// oder normal zu behandeln ist.

		$datalist = array();
		$isAssoc  = WV_Datatype::call($data['datatype'], 'usesAssociativeResults');

		if ($getOnlyExisting) {
			$data = self::getUserDataForToken($attribute, null);

			foreach ($data as $d) {
				$datalist = $isAssoc ? self::merge($datalist, $d->getValue()) : ($datalist + $d->getValue());
			}

			$datalist = array_unique($datalist);
		}
		else {
			$datalist = WV_Datatype::call($data['datatype'], 'extractValuesFromParams', $data['params']);
		}

		return $datalist;
	}
	
	/**
	 * Prüfen, ob Wert vorhanden ist
	 *
	 * Diese Methode prüft, ob ein bstimmter Benutzer einen bestimmten Wert
	 * besitzt.
	 *
	 * @param  mixed $user       der Benutzer
	 * @param  mixed $attribute  das Attribut
	 * @param  mixed $value      der gesuchte Wert
	 * @return boolean           true, wenn der Benutzer den gesuchte Wert bestitzt, sonst false
	 */
	public static function hasUserValue($user, $attribute, $value)
	{
		$data = self::getDataForUser($user, $attribute, null, $user->getSetID());
		
		if (!$data) {
			return false;
		}

		$v = $value->getValue();

		if (!is_array($v)) {
			return $value == $v;
		}
		
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
	public static function getUserDataForAttribute($attribute = null, $userType = null)
	{
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
	public static function getUserData($params = null)
	{
		// Die Parameter können auch als JSON-kodierter String angegeben werden.
		
		if (!is_array($params)) {
			if (!function_exists('json_decode')) {
				throw new WV16_Exception('Die Eingabedaten konnten nicht als JSON verarbeitet werden, da das entsprechende PHP-Modul nicht geladen ist.');
			}
			
			$params = json_decode($params, true);
		}
		
		// Query vorbereiten
		
		$query =
			'SELECT uv.* '.
			'FROM #_wv16_user_values uv '.
			'LEFT JOIN #_wv16_attributes attr ON uv.attribute_id = attr '.
			'LEFT JOIN #_wv16_users u ON uv.user_id = u.id '.
			'WHERE %where%1';

		// Parameter auspacken. extract() wäre uns zu unsicher, daher lieber
		// Stück für Stück von Hand.

		$userIDs = wv_makeArray(isset($params['userID']) ? $params['userID'] : null);
		$typeIDs = wv_makeArray(isset($params['typeID']) ? $params['typeID'] : null);
		$names   = wv_makeArray(isset($params['name'])   ? $params['name']   : null);
		$ids     = wv_makeArray(isset($params['id'])     ? $params['id']     : null);

		// Minimieren

		$userIDs = array_unique($userIDs);
		$typeIDs = array_unique($typeIDs);
		$names   = array_unique($names);
		$ids     = array_unique($ids);

		// In Query einsetzen
		
		$params = array();
		
		if (!empty($userIDs)) {
			$markers = WV_SQLEx::getMarkers(count($userIDs));
			$query   = str_replace('%where%', 'user_id IN ('.$markers.') AND %where%', $query);
			$params  = $userIDs;
		}
		
		if (!empty($typeIDs)) {
			$markers = WV_SQLEx::getMarkers(count($typeIDs));
			$query   = str_replace('%where%', 'type_id IN ('.$markers.') AND %where%', $query);
			$params  = array_merge($params, $userIDs);
		}
		
		if (!empty($names)) {
			$markers = WV_SQLEx::getMarkers(count($names));
			$query   = str_replace('%where%', 'attr.name IN ('.$markers.') AND %where%', $query);
			$params  = array_merge($params, $names);
		}
		
		if (!empty($ids)) {
			$markers = WV_SQLEx::getMarkers(count($ids));
			$query   = str_replace('%where%', 'attr.id IN ('.$markers.') AND %where%', $query);
			$params  = array_merge($params, $ids);
		}

		$query = str_replace('%where%', '', $query);

		// Query ist (fast) fertig.

		$orderby   = isset($params['orderby'])   ? $params['orderby']   : 'u.id';
		$direction = isset($params['direction']) ? $params['direction'] : 'ASC';
		$query    .= ' ORDER BY '.$orderby.' '.$direction;
		$result    = array();
		
		// Daten sammeln
		
		$sql->queryEx($query, $params, '#_', WV_SQLEx::RETURN_FALSE);

		foreach ($sql as $row) {
			$result[] = new _WV16_UserValue($row['value'], $row['attribute_id'], $row['user_id']);
		}

		return $result;
	}
}
