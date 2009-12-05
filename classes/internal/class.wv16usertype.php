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

class _WV16_UserType {
	private $id;          ///< int     die ID
	private $name;        ///< string  der interne Name
	private $title;       ///< string  der angezeigte Titel
	private $attributes;  ///< array   Liste von Attribut-IDs, die diesem Typ zugeordnet sind
	private $origAttributes;
	
	private static $instances = array();
	
	public static function getInstance($idOrName, $prefetchedData = array()) {
		$id = is_string($idOrName) ? self::getIDForName($idOrName) : intval($idOrName);
		if (empty(self::$instances[$id])) self::$instances[$id] = new self($id, $prefetchedData);
		return self::$instances[$id];
	}
	
	private function __construct($id, $prefetchedData = array()) {
		$sql  = WV_SQL::getInstance();
		$data = $prefetchedData ? $prefetchedData : $sql->fetch('*', 'wv16_utypes', 'id = '.$id);
		
		if ( !$data ) throw new Exception('Der Benutzertyp "'.$id.'" konnte nicht gefunden werden!');
		
		$this->id             = (int) $data['id'];
		$this->name           = $data['name'];
		$this->title          = $data['title'];
		$this->attributes     = array_map('intval', $sql->getArray('SELECT attribute_id FROM #_wv16_utype_attrib WHERE user_type = '.$this->id, '#_'));
		$this->origAttributes = $this->attributes;
	}
	
	public static function exists($typeID) {
		$typeID = intval($typeID);
		return WV_SQL::getInstance()->count('wv16_utypes', 'id = '.$typeID) == 1;
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
	public static function getIDForName($name) {
		$id = WV_SQL::getInstance()->fetch('id', 'wv16_utypes', 'LOWER(name) = LOWER("'.mysql_real_escape_string($name).'")');
		if (!$id) throw new Exception('Der Benutzertyp "'.$name.'" konnte nicht gefunden werden!');
		return intval($id);
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
	public function update() {
		$sql = WV_SQL::getInstance();
		
		// Auf Eindeutigkeit des Namens prüfen
		
		if ($sql->count('wv16_utypes','LOWER(name) = LOWER("'.mysql_real_escape_string($this->name).'") AND id <> '.$this->id) != 0)
			throw new Exception('Dieser interne Name ist bereits vergeben.');
		
		// Daten aktualisieren
		
		$query = sprintf('UPDATE %swv16_utypes SET name = "%s", title = "%s" WHERE id = %d',
			WV_SQL::getPrefix(),
			mysql_real_escape_string($this->name),
			mysql_real_escape_string($this->title),
			$this->id
		);
		
		if (!$sql->query($query)) throw new Exception($sql->getError());
		
		// Zugeordnete Metatypen aktualisieren
		
		$sql->query('DELETE FROM #_wv16_utype_attrib WHERE user_type = '.$this->id, '#_');
		
		foreach ($this->attributes as $aid) {
			$sql->query(
				'INSERT INTO #_wv16_utype_attrib '.
				'(user_type,attribute_id) VALUES ('.$this->id.','.intval($aid).')', '#_');
		}
		
		// Nun löschen wir noch alle Metainfo-Angaben zu allen Artikeln, die nicht
		// mehr zu diesem Artikeltyp gehören. Dazu brauchen wir zuerst alle Artikel,
		// die diesen Artikeltyp besitzen.
		
		$users = $sql->getArray('SELECT id FROM #_wv16_users WHERE type_id = '.$this->id, '#_');
		
		// Nun können wir diesen Artikeln die nicht mehr erlaubten Metainfos wegnehmen.
		
		if (!empty($users)) {
			$users = array_map('intval', $users);
			$query = 'DELETE FROM #_wv16_user_values WHERE user_id IN ('.implode(',', $users).')';
			
			if (empty($this->attributes)) $sql->query($query, '#_');
			else $sql->query($query.' AND attribute_id NOT IN ('.implode(',', $this->attributes).')', '#_');
		}
		
		// Falls mehr Metainfos diesem Typ zugewiesen wurden, übernehmen wir den Standardwert
		// dieser neuen Metainfos in die dazugehörigen Artikel.
		
		$newAttributes = array_diff($this->attributes, $this->origAttributes);
		
		foreach ($newAttributes as $attr) {
			$attr = _WV16_Attribute::getInstance($attr);
			$sql->query('INSERT INTO '.WV_SQL::getPrefix().'wv16_user_values '.
				'SELECT id,'.$attr->getID().',"'.mysql_real_escape_string($attr->getDefaultValue()).'" '.
				'FROM '.WV_SQL::getPrefix().'wv16_users WHERE id IN ('.implode(',', $users).')');
		}
		
		$this->origAttributes = $this->attributes;
	}
	
	public static function create($name, $title, $attributes) {
		$sql = WV_SQL::getInstance();
		
		// Auf Eindeutigkeit des Namens prüfen
		
		if ($sql->count('wv16_utypes', 'LOWER(name) = LOWER("'.mysql_real_escape_string($name).'")') != 0)
			throw new Exception('Dieser interne Name ist bereits vergeben.');
		
		// Daten eintragen
		
		$query = sprintf('INSERT INTO %swv16_utypes (name,title) VALUES ("%s","%s")',
			WV_SQL::getPrefix(),
			mysql_real_escape_string($name),
			mysql_real_escape_string($title)
		);
		
		if (!$sql->query($query)) throw new Exception($sql->getError());
		
		$id = $sql->lastID();
		
		// Zuordnungen zu den Metainfos erzeugen
		
		foreach ($attributes as $aid) {
			$sql->query(
				'INSERT INTO #_wv16_utype_attrib '.
				'(user_type,attribute_id) VALUES ('.$id.','.intval($aid).')', '#_');
		}
		
		return new self($id);
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
	public function delete() {
		if ($this->id == _WV16::DEFAULT_USER_TYPE) throw new Exception('Der Standard-Benutzertyp kann nicht gelöscht werden!');
		
		$sql = WV_SQL::getInstance();
		
		// Welche Attribute gehörten zu diesem Typ?
		
		$attrThisType    = WV16_Users::getAttributesForUserType($this->id);
		$attrDefaultType = WV16_Users::getAttributesForUserType(_WV16::DEFAULT_USER_TYPE);
		$sql             = WV_SQL::getInstance();
		
		foreach ($attrDefaultType as $idx => $attr) $attrDefaultType[$idx] = $attr->getID();
		foreach ($attrThisType    as $idx => $attr) $attrThisType[$idx]    = $attr->getID();
		
		$attrToDelete = array_diff($attrDefaultType, $attrThisType);
		$attrToDelete = array_map('intval', $attrToDelete);
		
		// Daten löschen
		
		$sql->query('DELETE FROM #_wv16_utypes WHERE id = '.$this->id, '#_');
		$sql->query('DELETE FROM #_wv16_utype_attrib WHERE user_type = '.$this->id, '#_');
		$sql->query('UPDATE #_wv16_users SET type_id = '._WV16::DEFAULT_USER_TYPE.' WHERE type_id = '.$this->id, '#_');
		$sql->query('DELETE FROM #_wv16_user_values WHERE attribute_id IN ('.implode(',', $attrToDelete).')', '#_');
	}
	
	/**
	 * Artikel-Formular-Erweiterung
	 * 
	 * Diese Methode erzeugt sowohl für die Slice, als auch für die
	 * Non-Slice-Seite eine Auswahlbox aller verfügbaren Artikeltypen. Dabei
	 * werden zusätzlich JavaScript-Elemente ausgegeben, die später dazu dienen,
	 * nur die jeweils benötigten Metainfo-Felder anzuzeigen, wenn sich die
	 * Auswahl des Artikeltyps ändert.
	 * 
	 * @param  array $params  die Parameter vom Extension-Point
	 * @return string         das Formular-Element oder ein leerer String, wenn der Artikel nicht ermittelt werden konnte
	 */
	public static function articleExtension($params) {
		preg_match('~&amp;article_id=(\d+)&amp;~i', $params['subject'], $matches);
		
		$article = 
			isset($params['article_id']) ? $params['article_id'] :
			(isset($params['id']) ? $params['id'] :
			(isset($matches[1]) ? intval($matches[1]) : -1));
		
		if ( $article < 0 ) return '';
		
		return _WV2::includeTemplate('articles/article_extension.phtml', array(
			'articleType' => WV2_MetaProvider::getArticleType($article),
			'article'     => $article,
			'params'      => $params
		));
	}
	
	
	/*
	   ************************************************************
	     Getter & Setter
	   ************************************************************
	*/
	
	/** @name Getter */
	
	/*@{*/
	
	/**
	 * Getter
	 * 
	 * Diese Methode gibt die gewünschte Eigenschaft ungefiltert zurück.
	 * 
	 * @return mixed  die entsprechende Eigenschaft
	 */
	public function getId()           { return $this->id;         }
	public function getName()         { return $this->name;       }
	public function getTitle()        { return $this->title;      }
	public function getAttributeIDs() { return $this->attributes; }
	
	public function getAttributes() {
		$return = array();
		foreach ( $this->attributes as $id ) $return[] = _WV16_Attribute::getInstance($id);
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
	public function setName($value) {
		$this->name = $value;
	}
	
	public function setTitle($value) {
		$this->title = $value;
	}
	
	public function setAttributes($value) {
		$this->attributes = array_map('intval', is_array($value) ? $value : array($value));
	}
	
	/* @} */
}
