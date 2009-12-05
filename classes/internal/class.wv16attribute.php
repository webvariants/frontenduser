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

class _WV16_Attribute {
	private $id;             ///< int      die interne ID
	private $name;           ///< string   der interne Name
	private $title;          ///< string   der angezeigte Name (Titel)
	private $position;       ///< int      Position in der Sortierreihenfolge
	private $datatype;       ///< int      die Datentyp-ID
	private $params;         ///< string   die Datentyp-Parameter
	private $defaultValue;   ///< string   der Standardwert
	private $userTypes;      ///< array    Liste von Benutzertypen, denen diese Information zugewiesen ist
	private $origUserTypes;
	
	private static $instances = array();
	
	public static function getInstance($idOrName, $prefetchedData = array()) {
		if (is_string($idOrName)) $id = self::getIDForName($idOrName, $type);
		else $id = intval($idOrName);
		
		if (empty(self::$instances[$idOrName])) self::$instances[$idOrName] = new self($idOrName, $prefetchedData);
		return self::$instances[$idOrName];
	}

	private function __construct($id, $prefetchedData = array()) {
		$sql = WV_SQL::getInstance();
		
		if (!$prefetchedData) $prefetchedData = $sql->fetch('*', 'wv16_attributes', 'id = '.$id);
		if (!$prefetchedData) throw new Exception('Das Attribut "'.$id.'" konnte nicht gefunden werden!');

		$this->id            = (int) $prefetchedData['id'];
		$this->name          = $prefetchedData['name'];
		$this->title         = $prefetchedData['title'];
		$this->position      = (int) $prefetchedData['position'];
		$this->datatype      = (int) $prefetchedData['datatype'];
		$this->params        = $prefetchedData['params'];
		$this->defaultValue  = $prefetchedData['default_value'];
		$this->userTypes     = $sql->getArray('SELECT user_type FROM #_wv16_utype_attrib WHERE attribute_id = '.$this->id, '#_');
		$this->userTypes     = array_map('intval', $this->userTypes);
		$this->origUserTypes = $this->userTypes;
	}
	
	/**
	 * Datensatz aktualisieren
	 * 
	 * Diese Methode prüft und speichert die über die Setter-Methoden gemachten
	 * Änderungen permanent in der Datenbank. Sollte es dabei zu einem Konflikt
	 * mit den Datentypen kommen, so wird die Methode standardmäßig die
	 * notwendigen Konvertierungen direkt vornehmen, es sei denn, ihr wird dies
	 * mit $convertDataIfRequired verboten.
	 * 
	 * Geprüft wird, ob die nötigen Rechte bestehen, die Metainformation zu
	 * bearbeiten und ob der (ggf. neue, geänderte) interne Name immer noch
	 * eindeutig ist.
	 * 
	 * Zusätzlich werden, falls sich die Zuordnung zu Artikeltypen geändert hat,
	 * die nun nicht mehr benötigten / erlaubten Metadaten von Objekten entfernt.
	 * 
	 * @throws Exception                       falls nicht genug Rechte bestehen oder der interne Name nicht eindeutig ist
	 * @param  boolean $convertDataIfRequired  wenn true, werden Konflikte beim Datentyp automatisch bearbeitet
	 * @param  boolean $applyDefaults          wenn true wird der Standardwert auf alle Artikel mit dieser Metainformation angewandt
	 * @param  boolean $lint                   wenn true werden die Daten nur geprüft aber keine Änderungen an der DB vorgenommen
	 */
	public function update($convertDataIfRequired = true, $applyDefaults = false, $lint = false) {
		if ( !_WV2::isAllowed('metainfo_defaults') ) throw new Exception('Das Bearbeiten von Metainformationen ist dir nicht erlaubt.');
		
		$sql = WV_SQL::getInstance();
		
		// Auf Eindeutigkeit des Namens prüfen
		
		if ( $sql->count('16_attributes', 'LOWER(name) = LOWER("'.mysql_real_escape_string($this->name).'") AND id <> '.$this->id) != 0 )
			throw new Exception('Dieser interne Name ist bereits vergeben.');
		
		// Alte Parameter für diese Metainfo holen
		
		list($oldDatatype, $oldParams) = array_values(self::getDatatypeWithParams($this));
		$oldPosition = $sql->fetch('position', 'wv16_attributes', 'id = '.$this->id);
		
		// Daten aktualisieren
		
		$query = sprintf('UPDATE %swv16_attributes SET name = "%s", title = "%s", datatype = %d, '.
			'params = "%s", default_value = "%s" WHERE id = %d',
			WV_SQL::getPrefix(),
			mysql_real_escape_string($this->name),
			mysql_real_escape_string($this->title),
			$this->datatype,
			mysql_real_escape_string($this->params),
			mysql_real_escape_string($this->defaultValue),
			$this->id
		);
		
		if ( !$sql->query($query) ) throw new Exception($sql->getError());
		
		///////////////////////////////////////////////////////////////////////
		// Zugeordnete Artikeltypen aktualisieren
		
		$sql->query('DELETE FROM #_wv16_utype_attrib WHERE attribute_id = '.$this->id, '#_');
		
		foreach ($this->userTypes as $tid) {
			$sql->query(
				'INSERT INTO #_wv16_utype_attrib (attribute_id,user_type) '.
				'VALUES ('.$this->id.','.intval($tid).')', '#_');
		}
		
		///////////////////////////////////////////////////////////////////////
		// Updates verarbeiten
		
		if ($convertDataIfRequired) {
			$this->handleUpdate($oldDatatype, $oldParams);
		}
		
		///////////////////////////////////////////////////////////////////////
		// Metainfo ggf. von Artikeltypen entfernen
		
		if (!empty($this->userTypes)) {
			$users = $sql->getArray('SELECT id FROM #_wv16_users WHERE 1', '#_');
		}
		else {
			$users = $sql->getArray(
				'SELECT id FROM #_wv16_users '.
				'WHERE type_id NOT IN ('.implode(',', $this->userTypes).')', '#_');
		}
		
		// Von allen *anderen* Artikeln entfernen wir nun diese Metainformation.
		
		$users = array_map('intval', $users);
		$sql->query(
			'DELETE FROM #_wv16_user_values '.
			'WHERE attribute_id = '.$this->id.' '.
			'AND user_id IN ('.implode(',', $users).')', '#_');
		
		// Wenn die Menge der Artikeltypen geändert hat, fügen wir den Standardwert
		// dieser Info allen Artikeln, die zu den *NEUEN* Artikeltypen gehören, hinzu.
		// Das tun wir aber nur, wenn der Anwender den Standardwert nicht eh auf
		// alle Objekte anwenden möchte. Wenn er das möchte, wird das nach diesem if
		// geregelt.
		
		if (!$applyDefaults) {
			$newTypes = array_diff($this->userTypes, $this->origUserTypes);
			$users    = $sql->getArray('SELECT id FROM #_wv16_users WHERE type_id IN ('.implode(',', $newTypes).')', '#_');
			
			if (!empty($articles)) {
				$sql->query('INSERT INTO '.WV_SQL::getPrefix().'wv16_user_values '.
					'SELECT id,'.$this->id.',"'.mysql_real_escape_string($this->defaultValue).'" '.
					'FROM '.WV_SQL::getPrefix().'wv16_users WHERE id IN ('.implode(',', $users).')');
			}
		}
		
		$this->origUserTypes = $this->userTypes;
		
		if ($applyDefaults) {
			$users = $sql->getArray('SELECT id FROM #_wv16_users WHERE type_id IN ('.implode(',', $this->userTypes).')', '#_');
			
			if (!empty($users)) {
				$sql->query('DELETE FROM #_wv16_user_values WHERE user_id IN ('.implode(',', $users).')', '#_');
				$sql->query('INSERT INTO '.WV_SQL::getPrefix().'wv16_user_values '.
					'SELECT id,'.$this->id.',"'.mysql_real_escape_string($this->defaultValue).'" '.
					'FROM '.WV_SQL::getPrefix().'wv16_users WHERE id IN ('.implode(',', $users).')');
			}
		}
	}
	
	/**
	 * Neues Attribut erzeugen
	 * 
	 * @throws Exception               falls der interne Name nicht eindeutig ist
	 * @param  string  $name           interner Name
	 * @param  string  $title          angezeigter Titel
	 * @param  int     $datatype       Datentyp-ID
	 * @param  string  $params         Datentyp-Parameter
	 * @param  string  $defaultOption  der Standardwert (abhängig vom Datentyp)
	 * @param  array   $userTypes      Liste von Benutzertyp-IDs (int)
	 * @return _WV16_Attribute         das neu erzeugte Objekt
	 */
	public static function create($name, $title, $datatype, $params, $defaultOption, $userTypes) {
		$sql = WV_SQL::getInstance();
		
		// Daten prüfen
		
		$name  = trim($name);
		$title = trim($title);
		
		if (empty($name)) throw new Exception('Der interne Name darf nicht leer sein.');
		if (empty($title)) throw new Exception('Der Titel darf nicht leer sein.');
		
		if ($sql->count('wv16_attributes', 'LOWER(name) = LOWER("'.mysql_real_escape_string($name).'")') != 0)
			throw new Exception('Dieser interne Name ist bereits vergeben.');
		
		// Position ermitteln
		
		$pos   = $sql->fetch('MAX(position)', 'wv16_attributes');
		$query = sprintf('INSERT INTO %swv16_attributes (name,title,datatype,params,default_value,'.
			'position) VALUES ("%s","%s",%d,"%s","%s",%d)',
			WV_SQL::getPrefix(),
			mysql_real_escape_string($name),
			mysql_real_escape_string($title),
			intval($datatype),
			mysql_real_escape_string($params),
			mysql_real_escape_string($defaultOption),
			intval($pos) + 1
		);
		
		if (!$sql->query($query)) throw new Exception($sql->getError());
		
		$id = intval($sql->lastID());
		
		// Zugeordnete Artikeltypen aktualisieren
		
		if (!empty($userTypes)) {
			foreach ($userTypes as $tid) {
				$sql->query(
					'INSERT INTO #_wv16_utype_attrib '.
					'(user_type,attribute_id) VALUES ('.intval($tid).','.$id.')', '#_');
			}
		}
		
		// Standardwert übernehmen
		
		$users = $sql->getArray('SELECT id FROM #_wv16_users WHERE type_id IN ('.implode(',', $userTypes).')', '#_');
		$query = 'INSERT INTO %swv16_user_values (user_id,attribute_id,value) VALUES (%d,%d,"%s")';
		
		// Queries in zwei Schleifen... aua ... da locken wir die Tabellen wenigstens. Sofern wir das dürfen.
		if (!empty($users)) $sql->query('LOCK TABLES #_wv16_user_values WRITE', '#_');
		
		global $REX;
		
		foreach ($users as $userID) {
			$sql->query(sprintf($query, WV_SQL::getPrefix(), $userID, $id, $defaultOption));
		}
		
		if (!empty($users)) $sql->query('UNLOCK TABLES');
		
		return new self($id);
	}
	
	public function delete() {
		$sql = WV_SQL::getInstance();
		$sql->query('DELETE FROM #_wv16_attributes WHERE id = '.$this->id, '#_');
		$sql->query('DELETE FROM #_wv16_utype_attrib WHERE attribute_id = '.$this->id, '#_');
		$sql->query('DELETE FROM #_wv16_user_values WHERE attribute_id = '.$this->id, '#_');
		
		// Attribute neu durchnummerieren
		$sql->query('UPDATE #_wv16_attributes SET position = position - 1 WHERE position > '.$this->position, '#_');
	}
	
	/**
	 * Verschieben
	 * 
	 * Diese Methode wird vom Ajax-Handler aufgerufen, um eine Metainformation
	 * zu verschieben. Die neue Position ist dabei eindeutig innerhalb von
	 * Metainfo-Typen (d.h., die drei Listen von Metainfos werden einzeln
	 * nummeriert).
	 * 
	 * @param int $position  die neue Position (1 bis n)
	 */
	public function shift($position) {
		$position = intval($position);
		
		if ( $position == $this->position ) return;
		if ( $position < 1 ) $position = 1;
		
		$sql         = WV_SQL::getInstance();
		$maxPosition = $sql->fetch('MAX(position)', 'wv16_attributes');
		
		if ( $position > $maxPosition ) $position = $maxPosition;
		
		$relation    = $position < $this->position ? '+' : '-';
		list($a, $b) = $position < $this->position ?
			array($position, $this->position) :
			array($this->position, $position);
		
		$sql->query(
			'UPDATE #_wv16_attributes '.
			'SET position = position '.$relation.' 1 '.
			'WHERE position BETWEEN '.$a.' AND '.$b, '#_');
		
		$sql->query('UPDATE #_wv16_attributes SET position = '.$position.' WHERE id = '.$this->id, '#_');
	}
	
	/**
	 * Aktualisierung und Kompatibilität verarbeiten
	 * 
	 * Diese Methode wird von update() aufgerufen, um sicherzustellen, dass die
	 * Datentyp-Parameter bzw. der alte und der neue Datentyp kompatibel sind.
	 * Wenn sie zum Einsatz kommt, hat der Controller den Benutzer bereits über
	 * die notwendigen Änderungen (falls zutreffend) informiert und von ihm die
	 * ausdrückliche Bestätigung eingeholt, dass die Konvertierungen durchgeführt
	 * werden dürfen.
	 * 
	 * @param int   $oldDatatype  die ID des alten Datentyps
	 * @param mixed $oldParams    die alten Datentyp-Parameter
	 */
	private function handleUpdate($oldDatatype, $oldParams) {
		// Wenn sich der Datentyp geändert hat, konvertieren wir.
		// Falls es keinen Konverter gibt, löschen wir die Metadaten.

		if ($oldDatatype != $this->datatype) {
			$converter = new _WV2_ConvertManager($oldDatatype, $this->datatype, $oldParams, $this->params);
			if ($converter->isConvertible()) $converter->convert($this->id);
			else _WV2::removeObjectMetaData($this->name, null, -1, $this->type);
		}
		else {
			// Falls sich die Parameter geändert haben, fragen wir den
			// Datentyp, ob er die Daten weiter verwenden möchte oder
			// ob sie inkompatibel wurden. Falls sie inkompatibel sind,
			// weisen wir ihn an, die Daten zu konvertieren oder
			// selbstständig die betroffenen Artikel zu bereinigen.

			if ($oldParams != $this->params) {
				$compatible = _WV2::callForDatatype($oldDatatype, 'checkParamCompatibility', array(null, $oldParams, $this->params, false));

				if (is_array($compatible)) {
					_WV2::callForDatatype($oldDatatype, 'removeIncompatibleData', array($this->name, $oldParams, $this->params, $this->type));
				}
			}
		}
	}
	
	public static function checkCompatibility($confirmed, $attribute, $newDatatype) {
		if ( $confirmed ) return true;
		return true; // vorerst deaktivieren wir das mal...
		
		$confirmationRequired = false;
		list($newParams,)     = _WV2::callForDatatype($newDatatype, 'serializeBackendForm', $attribute);
		$oldParams            = $attribute->getParams();
		$convertible          = null;
		$datatypeChanged      = $newDatatype != $attribute->getDatatype();

		if ($datatypeChanged) {
			$converter   = new _WV16_ConvertManager($attribute->getDatatype(), $newDatatype, $oldParams, $newParams);
			$convertible = $converter->isConvertible();
			$errorInfo   = 'Der Datentyp hat sich geändert. Eine Konvertierung ist '.($convertible ? 'jedoch <u>automatisiert</u>' : '<u>nicht</u>').' möglich.';
			$errorInfo   = array($errorInfo, WV2_MetaProvider::getObjectsWithMetaData($metainfo->getName(), null, null, null, null, WV2::CLANG_ALL, $type));
			$confirmationRequired = true;
		}
		else {
			$errorInfo            = _WV2::callForDatatype($newDatatype, 'checkParamCompatibility', array($attribute, $oldParams, $newParams, true));
			$confirmationRequired = is_array($errorInfo);
		}
		
		if (!$confirmationRequired) return true;

		include _WV16_PATH.'templates/attributes/confirmation.phtml';
		return false;
	}
	
	/**
	 * Artikel-Erweiterung
	 * 
	 * Diese Methode erzeugt das Formular für die Nonslice (Meta)-Seite eines
	 * Artikels. In dem Formular ist ausschließlich der Teil mit den Metainfos
	 * enthalten, die Auswahl für den Artikeltyp muss separat hinzugefügt werden.
	 * In dem Formular sind Felder für alle betreffenden Metainfos enthalten,
	 * nicht nur für eine bestimmte.
	 * 
	 * @param  array  $params  die von Redaxo an den Extension-Point übergebenen Parameter
	 * @return string          das fertige Formular
	 */
	public static function articleExtension($params) {
		$article     = intval($params['id']);
		$articleType = WV2_MetaProvider::getArticleType($article);
		if ($articleType == -1) $articleType = _WV2::DEFAULT_ARTICLE_TYPE;
		
		$availableInfos = WV2_MetaProvider::getAllArticleMetaInfos();
		$assignedData   = WV2_MetaProvider::getMetaDataForArticle($article);
		$requiredInfos  = WV2_MetaProvider::getMetaInfosForArticleType($articleType);
		
		ob_start();
		require _WV2_PATH.'templates/articles/meta_frontend_nonslice.phtml';
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}
	
	/**
	 * Artikel-Erweiterung
	 * 
	 * Diese Methode erzeugt das Formular für die Slice (Modul)-Seite eines
	 * Artikels. In dem Formular ist ausschließlich der Teil mit den Metainfos
	 * enthalten, die Auswahl für den Artikeltyp muss separat hinzugefügt werden.
	 * In dem Formular sind Felder für alle betreffenden Metainfos enthalten,
	 * nicht nur für eine bestimmte.
	 * 
	 * @param  array  $params  die von Redaxo an den Extension-Point übergebenen Parameter
	 * @param  int    $clang   die aktuelle Sprache
	 * @return string          das fertige Formular
	 */
	public static function articleSliceExtension($params, $clang) {
		// Frontend nicht mehrfach einblenden
		
		if ( self::$metainfoFrontendDisplayed ) return '';
		self::$metainfoFrontendDisplayed = true;
		
		// Wir müssen die Artikel-ID herausfinden...
		
		if ( !preg_match('~&amp;article_id=(\d+)&amp;~i', $params['subject'], $matches) ) return $params['subject'];
		$article = intval($matches[1]);
		
		// Ausgabe vorbereiten
		
		$articleType = WV2_MetaProvider::getArticleType($article);
		if ( $articleType == -1 ) $articleType = _WV2::DEFAULT_ARTICLE_TYPE;
		
		$availableInfos = WV2_MetaProvider::getAllArticleMetaInfos();
		$assignedData   = WV2_MetaProvider::getMetaDataForArticle($article, null, null, $clang);
		$requiredInfos  = WV2_MetaProvider::getMetaInfosForArticleType($articleType);
		$addonDir       = implode('', array_slice(explode('.', WV2::getRedaxoVersion()), 0, 2));
		
		ob_start();
		require _WV2_PATH.'templates/articles/meta_frontend_slice_rex'.$addonDir.'.phtml';
		$content = ob_get_contents();
		ob_end_clean();
		
		$subject = str_replace(
			'<!-- *** OUTPUT OF ARTICLE-CONTENT-EDIT-MODE - START *** -->',
			'<!-- *** OUTPUT OF ARTICLE-CONTENT-EDIT-MODE - START *** -->'.$content,
			$params['subject']);
		
		return $subject;
	}
	
	/**
	 * Kategorie-Erweiterung
	 * 
	 * Diese Methode erzeugt das Metainfo-Formular für Kategorien.
	 * 
	 * @param  array  $params  die von Redaxo an den Extension-Point übergebenen Parameter
	 * @param  int    $clang   die aktuelle Sprache
	 * @return string          das fertige Formular
	 */
	public static function categoryExtension($params, $clang) {
		$category = intval($params['id']);
		
		$availableInfos = WV2_MetaProvider::getAllCategoryMetaInfos();
		if ( empty($availableInfos) ) return '';
		
		$assignedData = WV2_MetaProvider::getMetaDataForCategory($category);
		$addonDir     = implode('', array_slice(explode('.', WV2::getRedaxoVersion()), 0, 2));
		
		ob_start();
		require _WV2_PATH.'templates/categories/meta_frontend_rex'.$addonDir.'.phtml';
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}
	
	/**
	 * Medien-Erweiterung
	 * 
	 * Diese Methode erzeugt das Metainfo-Formular für Medien.
	 * 
	 * @param  array  $params  die von Redaxo an den Extension-Point übergebenen Parameter
	 * @param  int    $clang   die aktuelle Sprache
	 * @return string          das fertige Formular
	 */
	public static function mediumExtension($params, $clang) {
		$medium  = intval($params['file_id']);
		$clangID = rex_post('clangID', 'int', rex_get('clangID', 'int', WV2::clang()));
		
		$availableInfos = WV2_MetaProvider::getAllMediumMetaInfos();
		if ( empty($availableInfos) ) return '';
		
		$assignedData = WV2_MetaProvider::getMetaDataForMedium($medium, null, null, $clangID);
		$addonDir     = implode('', array_slice(explode('.', WV2::getRedaxoVersion()), 0, 2));
		
		ob_start();
		require _WV2_PATH.'templates/media/meta_frontend_rex'.$addonDir.'.phtml';
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}
	
	public static function getDatatypeWithParams($attribute) {
		if ( $attribute instanceof _Wv16_Attribute ) {
			return array('datatype' => $attribute->getDatatype(), 'params' => $attribute->getParams());
		}

		if ( is_string($metainfo) ) $attribute = self::getIdForName($attribute, $type);
		else $metainfo = intval($attribute);
		
		return WV_SQL::getInstance()->fetch('datatype,params', 'wv16_attributes', 'id = '.$attribute);
	}

	/**
	 * ID ermitteln
	 * 
	 * Gibt die ID einer Metainfo für deren Namen zurück.
	 * 
	 * @throws Exception     falls der Name nicht gefunden wurde
	 * @param  string $name  der interne Name der Metainformation
	 * @param  int    $type  der Typ der Metainformation
	 * @return int           die ID
	 */
	public static function getIDForName($name) {
		$id = WV_SQL::getInstance()->fetch('id', 'wv16_attributes', 'LOWER(name) = LOWER("'.mysql_real_escape_string($name).'")');
		if (!$id) throw new Exception('Das Attribut "'.$name.'" konnte nicht gefunden werden!');
		return intval($id);
	}
	
	/*@{*/

	/**
	 * Getter
	 * 
	 * Gibt die entsprechende Eigenschaft ungefiltert zurück.
	 * 
	 * @return mixed  die entsprechende Eigenschaft
	 */
	
	public function getId()            { return $this->id;            }
	public function getName()          { return $this->name;          }
	public function getTitle()         { return $this->title;         }
	public function getPosition()      { return $this->position;      }
	public function getDatatype()      { return $this->datatype;      }
	public function getParams()        { return $this->params;        }
	public function getDefaultValue()  { return $this->defaultValue;  }
	public function getDefaultOption() { return $this->defaultValue;  } // Kompatibilität zu MetaInfoEx
	public function getUserTypes()     { return $this->userTypes;     }
	
	/*@}*/
	
	
	
	/*@{*/

	/**
	 * Setter
	 * 
	 * Setzt die entsprechende Eigenschaft. Für alle bis auf den Artikeltyp
	 * wird das Recht metainfo_complete benötigt. Für den Artikeltyp benötigt
	 * man metainfo_articletype.
	 * Das Überprüfen der Werte übernimmt erst die update()-Methode.
	 * 
	 * @param mixed $value  der neue Wert
	 */

	public function setDatatype($value) {
		$this->datatype = intval($value);
	}

	public function setDefaultValue($value) {
		$this->defaultValue = $value;
	}

	public function setName($value) {
		if ( _WV2::isAllowed('metainfo_complete') ) {
			$value = trim($value);
			if (empty($value)) throw new Exception('Der interne Name darf nicht leer sein.');
			$this->name = $value;
		}
	}

	public function setParams($value) {
		if ( _WV2::isAllowed('metainfo_defaults') ) $this->params = $value;
	}

	public function setTitle($value) {
		if ( _WV2::isAllowed('metainfo_complete') ) {
			$value = trim($value);
			if (empty($value)) throw new Exception('Der Titel darf nicht leer sein.');
			$this->title = $value;
		}
	}

	public function setUserTypes($value) {
		$this->userTypes = array_map('intval', is_array($value) ? $value : array($value));
	}

	/*@}*/

	/**
	 * Position setzten
	 * 
	 * Verschiebt eine Metainformation. Ist nur noch Alias für shift().
	 * 
	 * @deprecated           lieber shift() benutzen
	 * @param int $position  die neue Position
	 */
	public function setPosition($position) {
		$this->shift(intval($position));
	}
}
