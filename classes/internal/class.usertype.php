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

class _WV16_UserType
{
	protected $id;          ///< int     die ID
	protected $name;        ///< string  der interne Name
	protected $title;       ///< string  der angezeigte Titel
	protected $attributes;  ///< array   Liste von Attribut-IDs, die diesem Typ zugeordnet sind
	protected $origAttributes;
	
	private static $instances = array();
	
	public static function getInstance($idOrName, $prefetchedData = array())
	{
		$id = self::getIDForName($idOrName);
		
		if (isset(self::$instances[$id])) {
			return self::$instances[$id];
		}
		
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.internal.usertypes';
		$instance  = $cache->get($namespace, $id);
		
		if (!$instance) {
			if ($cache->lock($namespace, $id)) {
				try {
					$instance = new self($id, $prefetchedData);
					$cache->set($namespace, $id, $instance);
					$cache->unlock($namespace, $id);
				}
				catch (Exception $e) {
					$cache->unlock($namespace, $id);
					throw $e;
				}
			}
			else {
				$instance = $cache->waitForObject($namespace, $id);
				
				if (!$instance) {
					$instance = new self($id, $prefetchedData);
				}
			}
		}
		
		self::$instances[$id] = $instance;
		return self::$instances[$id];
	}
	
	private function __construct($id, $prefetchedData = array())
	{
		$sql  = WV_SQLEx::getInstance();
		$data = $prefetchedData ? $prefetchedData : $sql->saveFetch('*', 'wv16_utypes', 'id = ?', $id);
		
		if (!$data) {
			throw new WV16_Exception('Der Benutzertyp #'.$id.' konnte nicht gefunden werden!');
		}
		
		$this->id             = (int) $data['id'];
		$this->name           = $data['name'];
		$this->title          = $data['title'];
		$this->attributes     = $sql->getArray('SELECT attribute_id FROM #_wv16_utype_attrib WHERE user_type = ?', $this->id, '#_');
		$this->origAttributes = $this->attributes;
	}
	
	public static function exists($typeID)
	{
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.internal.usertypes';
		
		if ($cache->exists($namespace, $typeID)) {
			return true;
		}
		
		$sql = WV_SQLEx::getInstance();
		return $sql->count('wv16_utypes', 'id = ?', (int) $typeID) == 1;
	}
	
	/**
	 * ID ermitteln
	 *
	 * Diese Methode ermittelt für einen internen Namen eines Artikeltyps die
	 * dazughörige ID.
	 *
	 * @throws Exception     falls der interne Name nicht gefunden wurde
	 * @param  string $name  der interne Name
	 * @return int           die gefundene ID
	 */
	public static function getIDForName($name)
	{
		if (WV_String::isInteger($name)) {
			return (int) $name;
		}
		
		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.internal.usertypes';
		$key       = WV_Cache::generateKey('mapping', strtolower($name));
		
		$id = $cache->get($namespace, $key, -1);
		
		if ($id > 0) {
			return (int) $id;
		}
		
		$sql = WV_SQLEx::getInstance();
		$id  = $sql->saveFetch('id', 'wv16_utypes', 'LOWER(name) = ?', strtolower($name));
		
		if (!$id) {
			throw new WV16_Exception('Der Benutzertyp "'.$name.'" konnte nicht gefunden werden!');
		}
		
		$cache->set($namespace, $key, (int) $id);
		return (int) $id;
	}
	
	/**
	 * Artikeltyp aktualisieren
	 *
	 * Diese Methode speichert und validiert alle Änderungen, die bisher mit den
	 * set*-Methoden auf diesem Objekt durchgeführt wurden, persistent in der
	 * Datenbank.
	 *
	 * @throws Exception  falls der interne Name bereits vergeben ist oder ein SQL-Fehler auftrat
	 */
	public function update($useTransaction = true)
	{
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			// Auf Eindeutigkeit des Namens prüfen
			
			if ($sql->count('wv16_utypes','LOWER(name) = ? AND id <> ?', array(strtolower($this->name), $this->id)) > 0) {
				throw new WV_InputException('Dieser interne Name ist bereits vergeben.');
			}
			
			// Daten aktualisieren
			
			$query = 'UPDATE #_wv16_utypes SET name = ?, title = ? WHERE id = ?';
			$sql->queryEx($query, array($this->name, $this->title, $this->id), '#_');
			
			// Zugeordnete Attribute aktualisieren
			// TODO: Das können wir effizienter.
			
			$sql->queryEx('DELETE FROM #_wv16_utype_attrib WHERE user_type = ?', $this->id, '#_');
			
			foreach ($this->attributes as $aid) {
				$sql->queryEx(
					'INSERT INTO #_wv16_utype_attrib (user_type,attribute_id) VALUES (?,?)',
					array($this->id, (int) $aid), '#_'
				);
			}
			
			// Nun löschen wir noch alle Attributwerte von allen Benutzern, die nicht
			// mehr zu diesem Benutzertyp gehören. Dazu brauchen wir zuerst alle Benutzer,
			// die diesen Benutzertyp besitzen.
			
			$users = $sql->getArray('SELECT id FROM #_wv16_users WHERE type_id = ?', $this->id, '#_');
			
			// Nun können wir diesen Benutzern die nicht mehr erlaubten Attribute wegnehmen.
			
			if (!empty($users)) {
				$users = implode(',', $users);
				$query = 'DELETE FROM #_wv16_user_values WHERE set_id >= 0 AND user_id IN ('.$users.')';
				
				if (empty($this->attributes)) {
					$sql->queryEx($query, array(), '#_');
				}
				else {
					$query .= ' AND attribute_id NOT IN ('.implode(',', $this->attributes).')';
					$sql->queryEx($query, array(), '#_');
				}
			
				// Falls mehr Attribute diesem Typ zugewiesen wurden, übernehmen wir den Standardwert
				// dieser neuen Attribute in die dazugehörigen Benutzer.
				
				$newAttributes = array_diff($this->attributes, $this->origAttributes);
				
				// TODO: Das können wir besser.
				
				if (!empty($newAttributes)) {
					$query =
						'INSERT INTO #_wv16_user_values '.
						'SELECT user_id,attribute_id,set_id,default_value '.
						'FROM #_wv16_user_values uv, #_wv16_attributes a '.
						'WHERE uv.attribute_id = a.id AND uv.set_id >= 0 '.
						'AND user_id IN ('.$users.') AND id IN ('.implode(',', $newAttributes).')';
					
					$sql->queryEx($query, array(), '#_');
				}
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser', true);
			
			$this->origAttributes = $this->attributes;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	public static function create($name, $title, $attributes, $useTransaction = true)
	{
		$name  = trim($name);
		$title = trim($title);
		$sql   = WV_SQLEx::getInstance();
		$mode  = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			if (empty($name)) {
				throw new WV_InputException('Der Name muss angegeben werden!');
			}
			
			if (empty($title)) {
				throw new WV_InputException('Der Titel muss angegeben werden!');
			}
			
			// Auf Eindeutigkeit des Namens prüfen
			
			if ($sql->count('wv16_utypes', 'LOWER(name) = ?', strtolower($name)) > 0) {
				throw new WV_InputException('Dieser interne Name ist bereits vergeben.');
			}
			
			// Daten eintragen
			
			$query = 'INSERT INTO #_wv16_utypes (name,title) VALUES (?,?)';
			$sql->queryEx($query, array($name, $title), '#_');
			$id = $sql->lastID();
			
			// Zuordnungen zu den Metainfos erzeugen
			// TODO: Das können wir besser.
			
			foreach ($attributes as $aid) {
				$sql->queryEx(
					'INSERT INTO #_wv16_utype_attrib (user_type,attribute_id) VALUES (?,?)',
					array($id, (int) $aid), '#_'
				);
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser.internal', true); // external kann bestehen bleiben, sind nur unberührte Nutzerwerte
			
			return self::getInstance($id);
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return null;
		}
	}
	
	/**
	 * Artikeltyp löschen
	 *
	 * Diese Methode löscht einen Artikeltyp. Dabei werden alle Artikel, die ihm
	 * zugewiesen waren, nun dem Standard-Artikeltyp zugewiesen. Dabei werden
	 * ebenfalls die Metadatum, die nicht zum Standardtyp gehören, entfernt.
	 *
	 * @throws Exception  falls versucht wird, den Standardtyp zu löschen
	 */
	public function delete($useTransaction = true)
	{
		if ($this->id == _WV16::DEFAULT_USER_TYPE) {
			throw new WV16_Exception('Der Standard-Benutzertyp kann nicht gelöscht werden!');
		}
		
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);
		
		try {
			$sql->startTransaction($useTransaction);
			
			// Welche Attribute gehörten zu diesem Typ?
			
			$attrThisType    = WV16_Users::getAttributesForUserType($this->id);
			$attrDefaultType = WV16_Users::getAttributesForUserType(_WV16::DEFAULT_USER_TYPE);
			
			foreach ($attrDefaultType as $idx => $attr) $attrDefaultType[$idx] = $attr->getID();
			foreach ($attrThisType    as $idx => $attr) $attrThisType[$idx]    = $attr->getID();
			
			$attrToDelete = array_diff($attrThisType, $attrDefaultType);
			$attrToDelete = array_map('intval', $attrToDelete);
			
			// Daten löschen
			
			$sql->queryEx('DELETE FROM #_wv16_utypes WHERE id = ?', $this->id, '#_');
			$sql->queryEx('DELETE FROM #_wv16_utype_attrib WHERE user_type = ?', $this->id, '#_');
			$sql->queryEx('UPDATE #_wv16_users SET type_id = ? WHERE type_id = ?', array(_WV16::DEFAULT_USER_TYPE, $this->id), '#_');
			
			if (!empty($attrToDelete)) {
				$sql->queryEx('DELETE FROM #_wv16_user_values WHERE attribute_id IN ('.implode(',', $attrToDelete).')', array(), '#_');
			}
			
			$sql->doCommit($useTransaction);
			$sql->setErrorMode($mode);
			
			$cache = WV_DeveloperUtils::getCache();
			$cache->flush('frontenduser', true);
			
			$attrToDelete    = null;
			$attrDefaultType = null;
			$attrThisType    = null;
			$markers         = null;
			
			return true;
		}
		catch (Exception $e) {
			$sql->cleanEndTransaction($useTransaction, $mode, $e, 'WV16_Exception');
			return false;
		}
	}
	
	/** @name Getter */
	
	/*@{*/
	
	/**
	 * Getter
	 *
	 * Diese Methode gibt die gewünschte Eigenschaft ungefiltert zurück.
	 *
	 * @return mixed  die entsprechende Eigenschaft
	 */
	public function getID()           { return $this->id;         }
	public function getName()         { return $this->name;       }
	public function getTitle()        { return $this->title;      }
	public function getAttributeIDs() { return $this->attributes; }
	
	public function getAttributes()
	{
		$return = array();
		
		foreach ($this->attributes as $id) {
			$return[] = _WV16_Attribute::getInstance($id);
		}
		
		return $return;
	}
	
	/*@}*/
	
	/** @name Setter */
	
	/*@{*/
	
	/**
	 * Setter
	 *
	 * Diese Methode setzt die Eigenschaft auf einen neuen Wert. Das Prüfen der
	 * Eingabe übernimmt erst die update()-Method der jeweiligen Instanz.
	 *
	 * @param mixed $value  der neue Wert der Eigenschaft
	 */
	public function setName($value)  { $this->name  = trim($value); }
	public function setTitle($value) { $this->title = trim($value); }
	
	public function setAttributes($value)
	{
		$attributes = wv_makeArray($value);
		
		$this->attributes = array();
		
		foreach ($attributes as $attr) {
			$id = @_WV16::getIDForAttribute($attr, false);
			
			if ($id > 0) {
				$this->attributes[] = $id;
			}
		}
		
		$this->attributes = array_unique($this->attributes);
	}
	
	/* @} */
}
