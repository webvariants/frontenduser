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

class _WV16_Attribute extends WV_Object implements _WV_IProperty {
	protected $id;           ///< int      die interne ID
	protected $name;         ///< string   der interne Name
	protected $title;        ///< string   der angezeigte Name (Titel)
	protected $helptext;     ///< string   der Hilfetext
	protected $position;     ///< int      Position in der Sortierreihenfolge
	protected $datatype;     ///< int      die Datentyp-ID
	protected $params;       ///< string   die Datentyp-Parameter
	protected $defaultValue; ///< string   der Standardwert
	protected $hidden;       ///< boolean  wenn true, soll das Attribut im Backend nicht angezeigt werden
	protected $userTypes;    ///< array    Liste von Benutzertypen, denen diese Information zugewiesen ist
	protected $deleted;      ///< boolean  Ist dieses Attribut gelöscht (und nur noch für die read-only Werte in der Datenbank)?

	private $origUserTypes;

	private static $instances = array();

	public static function getInstance($idOrName) {
		$id = self::getIDForName($idOrName);

		if (empty(self::$instances[$id])) {
			$callback = array(__CLASS__, '_getInstance');
			$instance = self::getFromCache('frontenduser.internal.attributes', $id, $callback, $id);

			self::$instances[$id] = $instance;
		}

		return self::$instances[$id];
	}

	protected static function _getInstance($id) {
		return new self($id);
	}

	private function __construct($id) {
		$sql  = WV_SQLEx::getInstance();
		$data = $sql->safeFetch('*', 'wv16_attributes', 'id = ?', $id);

		if (!$data) {
			throw new WV16_Exception('Das Attribut #'.$id.' konnte nicht gefunden werden!');
		}

		$this->id            = (int) $data['id'];
		$this->name          = $data['name'];
		$this->title         = $data['title'];
		$this->helptext      = $data['helptext'];
		$this->position      = (int) $data['position'];
		$this->datatype      = (int) $data['datatype'];
		$this->params        = $data['params'];
		$this->defaultValue  = $data['default_value'];
		$this->hidden        = (boolean) $data['hidden'];
		$this->deleted       = (boolean) $data['deleted'];
		$this->userTypes     = $sql->getArray('SELECT user_type FROM ~wv16_utype_attrib WHERE attribute_id = ?', $this->id, '~');
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
	 */
	public function update($convertDataIfRequired = true, $applyDefaults = false) {
		if ($this->deleted) return false;

		$sql = WV_SQLEx::getInstance();

		// Auf Eindeutigkeit des Namens prüfen

		if ($sql->count('wv16_attributes', 'LOWER(name) = ? AND deleted = 0 AND id <> ?', array(strtolower($this->name), $this->id)) > 0) {
			throw new WV16_Exception('Dieser interne Name ist bereits vergeben.');
		}

		return self::transactionGuard(array($this, '_update'), array($convertDataIfRequired, $applyDefaults), 'WV16_Exception');
	}

	protected function _update($convert, $apply) {
		$sql = WV_SQLEx::getInstance();

		///////////////////////////////////////////////////////////////////////
		// Alte Parameter für dieses Attribut holen

		list($oldDatatype, $oldParams) = array_values(self::getDatatypeWithParams($this));
		$oldPosition = $sql->fetch('position', 'wv16_attributes', 'id = ?', $this->id);

		///////////////////////////////////////////////////////////////////////
		// Prüfen, ob dieses Attribut in seiner jetzigen Form von read-only
		// Daten genutzt wird. Wenn ja, dürfen wir es nicht ändern und müssen
		// eine Kopie dieses Attributs erzeugen.

		$isUsed = $sql->count('wv16_user_values', 'attribute_id = ? AND set_id < 0', $this->id) ? true : false;
		if ($isUsed) $this->createCopy();

		///////////////////////////////////////////////////////////////////////
		// Daten aktualisieren

		$sql->queryEx(
			'UPDATE ~wv16_attributes SET name = ?, title = ?, helptext = ?, '.
			'datatype = ?, params = ?, default_value = ?, hidden = ? WHERE id = ?',
			array($this->name, $this->title, $this->helptext, $this->datatype,
			$this->params, $this->defaultValue, $this->hidden ? 1 : 0, $this->id), '~'
		);

		///////////////////////////////////////////////////////////////////////
		// Zugeordnete Benutzertypen aktualisieren

		$sql->queryEx('DELETE FROM ~wv16_utype_attrib WHERE attribute_id = ?', $this->id, '~');

		if (!empty($this->userTypes)) {
			$params  = array();
			$markers = WV_SQLEx::getMarkers(count($this->userTypes), '(?,?)');

			foreach ($this->userTypes as $tid) {
				$params[] = $this->id;
				$params[] = $tid;
			}

			$sql->queryEx('INSERT INTO ~wv16_utype_attrib (attribute_id,user_type) VALUES '.$markers, $params, '~');

			$params  = null;
			$markers = null;
		}

		///////////////////////////////////////////////////////////////////////
		// Updates verarbeiten

		if ($convert) {
			$this->handleUpdate($oldDatatype, $oldParams);
		}

		///////////////////////////////////////////////////////////////////////
		// Attribut ggf. von Benutzern entfernen

		if (empty($this->userTypes)) {
			$users = $sql->getArray('SELECT id FROM ~wv16_users WHERE 1', array(), '~');
		}
		else {
			$users = $sql->getArray('SELECT id FROM ~wv16_users WHERE type_id NOT IN ('.implode(',', $this->userTypes).')', array(), '~');
		}

		// Von allen *anderen* Benutzern entfernen wir nun dieses Attribut.
		// Allerdings betrifft dies nur Sets, die nicht als read-only markiert sind.

		if (!empty($users)) {
			$sql->queryEx(
				'DELETE FROM ~wv16_user_values WHERE attribute_id = ? AND set_id >= 0 AND user_id IN ('.implode(',', $users).')',
				$this->id, '~'
			);

			$users = null;
		}

		///////////////////////////////////////////////////////////////////////
		// Standardwert übernehmen bzw. neue Werte wegen neuer Benutzertypen anlegen

		if (!empty($this->userTypes)) {
			// Die Menge der Benutzer, für die wir den Standardwert dieses Attributs
			// ist entweder die Menge aller Benutzer, wenn wir den Standardwert eh
			// übernehmen wollen (if) oder nur die Menge der Benutzer, die den ggf. neuen
			// Benutzertypen angehören (else).

			if ($apply) {
				$users = $sql->getArray('SELECT id FROM ~wv16_users WHERE type_id IN ('.implode(',', $this->userTypes).')', array(), '~');
			}
			else {
				$newTypes = array_diff($this->userTypes, $this->origUserTypes);
				$users    = array();

				if (!empty($newTypes)) {
					$users = $sql->getArray('SELECT id FROM ~wv16_users WHERE type_id IN ('.implode(',', $newTypes).')', array(), '~');
				}
			}

			if (!empty($users)) {
				$sql->queryEx(
					'REPLACE INTO ~wv16_user_values '.
					'SELECT user_id,?,set_id,? FROM ~wv16_user_values '.
					'WHERE user_id IN ('.implode(',', $users).') AND set_id >= 0',
					array($this->id, $this->defaultValue) , '~'
				);
			}
		}

		$this->origUserTypes = $this->userTypes;

		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);

		return true;
	}

	private function createCopy() {
		$sql   = WV_SQLEx::getInstance();
		$oldID = $this->id;

		// Attribut-Datensatz kopieren und direkt mit neuen Werten füllen
		$sql->queryEx(
			'INSERT INTO ~wv16_attributes '.
			'SELECT NULL,name,title,helptext,position,datatype,params,default_value,hidden,0 FROM ~wv16_attributes WHERE id = ?',
			$this->id, '~'
		);

		$this->id      = $sql->lastID();
		$this->deleted = false; // nur um sicherzugehen

		// Den alten Datensatz als gelöscht / obsolet markieren
		$sql->queryEx('UPDATE ~wv16_attributes SET deleted = 1 WHERE id = ?', $oldID, '~');

		// Verknüpfung zu diesem Attribut bei den Benutzertypen ändern
		$sql->queryEx('UPDATE ~wv16_utype_attrib SET attribute_id = ? WHERE attribute_id = ?', array($this->id, $oldID), '~');

		// Verknüpfung bei den Benutzerdaten ändern (aber NUR bei den Live-Daten!)
		$sql->queryEx(
			'UPDATE ~wv16_user_values SET attribute_id = ? WHERE attribute_id = ? AND set_id >= 0',
			array($this->id, $oldID), '~'
		);
	}

	/**
	 * Neues Attribut erzeugen
	 *
	 * @throws Exception              falls der interne Name nicht eindeutig ist
	 * @param  string  $name          interner Name
	 * @param  string  $title         angezeigter Titel
	 * @param  string  $helptext      der Hilfetext
	 * @param  int     $datatype      Datentyp-ID
	 * @param  string  $params        Datentyp-Parameter
	 * @param  string  $defaultValue  der Standardwert (abhängig vom Datentyp)
	 * @param  boolean $hidden        wenn true, wird das Attribut im Backend nicht angezeigt
	 * @param  array   $userTypes     Liste von Benutzertyp-IDs (int)
	 * @return _WV16_Attribute        das neu erzeugte Objekt
	 */
	public static function create($name, $title, $helptext, $datatype, $params, $defaultValue, $hidden, $userTypes) {
		$sql = WV_SQLEx::getInstance();

		///////////////////////////////////////////////////////////////////////
		// Daten prüfen

		$name  = trim($name);
		$title = trim($title);

		if (empty($name)) {
			throw new WV16_Exception('Der interne Name darf nicht leer sein.');
		}

		if (empty($title)) {
			throw new WV16_Exception('Der Titel darf nicht leer sein.');
		}

		if ($sql->count('wv16_attributes', 'LOWER(name) = ? AND deleted = 0', strtolower($name)) != 0) {
			throw new WV16_Exception('Dieser interne Name ist bereits vergeben.');
		}

		$userTypes = array_unique(array_map('intval', $userTypes));
		$params    = array($name, $title, $helptext, $datatype, $params, $defaultValue, $hidden, $userTypes);

		return self::transactionGuard(array(__CLASS__, '_create'), $params, 'WV16_Exception');
	}

	protected static function _create($name, $title, $helptext, $datatype, $params, $defaultValue, $hidden, $userTypes) {
		///////////////////////////////////////////////////////////////////////
		// Position ermitteln & Attribut anlegen

		$sql = WV_SQLEx::getInstance();
		$pos = (int) $sql->fetch('MAX(position)', 'wv16_attributes');

		$sql->queryEx(
			'INSERT INTO ~wv16_attributes (name,title,helptext,datatype,params,default_value,'.
			'position,hidden,deleted) VALUES (?,?,?,?,?,?,?,?,?)',
			array($name, $title, $helptext, (int) $datatype, $params, $defaultValue,
			$pos + 1, $hidden ? 1 : 0, 0), '~'
		);

		$id = (int) $sql->lastID();

		///////////////////////////////////////////////////////////////////////
		// Zugeordnete Benutzertypen eintragen

		if (!empty($userTypes)) {
			$params  = array();
			$markers = WV_SQLEx::getMarkers(count($userTypes), '(?,?)');

			foreach ($userTypes as $tid) {
				$params[] = $tid;
				$params[] = $id;
			}

			$sql->queryEx('INSERT INTO ~wv16_utype_attrib (user_type,attribute_id) VALUES '.$markers, $params, '~');

			$params  = null;
			$markers = null;

			///////////////////////////////////////////////////////////////////////
			// Standardwert übernehmen

			$markers = implode(',', $userTypes);

			// 1. Selektiere all diejenigen Benutzer, die schon Werte (= Sets) haben.

			$select1 =
				'SELECT DISTINCT user_id,?,set_id,? '.
				'FROM ~wv16_user_values uv, ~wv16_users u '.
				'WHERE uv.user_id = u.id AND u.type_id IN ('.$markers.') AND set_id >= 0';

			// 2. Selektiere all diejenigen Benutzer, die noch keine Werte haben.
			// Das kann z.B. auftreten, wenn die Benutzer zu Typen gehören, die noch
			// keine Attribute hatten.

			$select2 =
				'SELECT DISTINCT id,?,1,? FROM ~wv16_users u '.
				'WHERE u.type_id IN ('.$markers.') AND u.id NOT IN '.
				'(SELECT DISTINCT user_id FROM ~wv16_user_values)';

			// 3. Vereinige diese beiden Mengen

			$select = $select1.' UNION '.$select2;

			// 4. Verwende dieses SELECT, um damit das INSERT-Statement zu befeuern.

			$query = 'INSERT INTO ~wv16_user_values (user_id,attribute_id,set_id,value) '.$select;
			$sql->queryEx($query, array($id, $defaultValue, $id, $defaultValue), '~');

			$markers = null;
		}

		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);

		return self::getInstance($id);
	}

	public function delete() {
		if ($this->deleted) return false;
		return self::transactionGuard(array($this, '_delete'), null, 'WV16_Exception');
	}

	protected function _delete() {
		$sql = WV_SQLEx::getInstance();

		// Prüfen, ob dieses Attribut in seiner jetzigen Form von read-only
		// Daten genutzt wird. Wenn ja, dürfen wir es nicht löschen und müssen
		// es nur auf deleted=1 setzen.

		$isUsed = $sql->count('wv16_user_values', 'attribute_id = ? AND set_id < 0', $this->id) ? true : false;

		if ($isUsed) {
			$sql->queryEx('UPDATE ~wv16_attributes SET deleted = 1 WHERE id = ?', $this->id, '~');
			$this->deleted = true;
		}
		else {
			$sql->queryEx('DELETE FROM ~wv16_attributes WHERE id = ?', $this->id, '~');
			$sql->queryEx('DELETE FROM ~wv16_user_values WHERE attribute_id = ?', $this->id, '~');
		}

		$sql->queryEx('DELETE FROM ~wv16_utype_attrib WHERE attribute_id = ?', $this->id, '~');
		$sql->queryEx('UPDATE ~wv16_attributes SET position = position - 1 WHERE position > ? AND deleted = 0', $this->position, '~');

		// Fertig!

		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);

		return true;
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
		if ($this->deleted) return false;

		$position = (int) $position;

		if ($position == $this->position) {
			return true;
		}

		if ($position < 1) {
			$position = 1;
		}

		return self::transactionGuard(array($this, '_shift'), $position, 'WV16_Exception');
	}

	protected function _shift($position) {
		$sql         = WV_SQLEx::getInstance();
		$maxPosition = $sql->fetch('MAX(position)', 'wv16_attributes', 'deleted = 0');

		if ($position > $maxPosition) {
			$position = $maxPosition;
		}

		$relation    = $position < $this->position ? '+' : '-';
		list($a, $b) = $position < $this->position ?
			array($position, $this->position) :
			array($this->position, $position);

		$sql->queryEx(
			'UPDATE ~wv16_attributes SET position = position '.$relation.' 1 WHERE position BETWEEN ? AND ? AND deleted = 0',
			array($a, $b), '~'
		);

		$sql->queryEx('UPDATE ~wv16_attributes SET position = ? WHERE id = ?', array($position, $this->id), '~');

		// Fertig!

		$cache = sly_Core::cache();
		$cache->flush('frontenduser', true);
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
	protected function handleUpdate($oldDatatype, $oldParams) {
		// Wenn sich der Datentyp geändert hat, konvertieren wir.
		// Falls es keinen Konverter gibt, löschen wir die Metadaten.

		$sql = WV_SQLEx::getInstance();

		if ($oldDatatype != $this->datatype) {
			$converter = new _WV_Convert_Manager($oldDatatype, $this->datatype, $oldParams, $this->params);

			if ($converter->isConvertible()) {
				$converter->convert($this->id);
			}
			else {
				$sql->queryEx('DELETE FROM ~wv16_user_values WHERE attribute_id = ?', $this->id, '~');
				$this->origUserTypes = array(); // Performance des nachfolgenden Codes in update() verbessern
			}
		}
		else {
			// Falls sich die Parameter geändert haben, fragen wir den
			// Datentyp, ob und wie er seine Daten reparieren möchte.

			if ($oldParams != $this->params) {
				$actionsToTake = WV_Datatype::call($oldDatatype, 'getIncompatibilityUpdateStatement', array($oldParams, $this->params));
				$prefix        = WV_SQLEx::getPrefix();

				foreach ($actionsToTake as $action) {
					list ($type, $what, $where) = $action;
					$what  = str_replace('$$$value_column$$$', 'value', $what);
					$where = str_replace('$$$value_column$$$', 'value', $where);

					switch ($type) {
						case 'DELETE':
							$sql->queryEx('DELETE FROM '.$prefix.'wv16_user_values WHERE '.$where);
							break;

						case 'UPDATE':
							$sql->queryEx('UPDATE '.$prefix.'wv16_user_values SET '.$what.' WHERE '.$where);
							break;

						default:
							trigger_error('Unbekannte Aktion incompatibility update statement!', E_USER_WARNING);
					}
				}
			}
		}
	}

	/**
	 * Datentyp/Parameter-Kompatibilität prüfen
	 *
	 * Diese Methode prüft, ob bei einer Datentyp-Änderung die neuen Typen
	 * ineinander konvertiert werden können. Falls sich die Parameter geändert
	 * haben, wird geprüft, ob diese weiterhin kompatibel sind.
	 *
	 * Tritt eine Inkompatibilität auf, wird der Benutzer informiert und eine
	 * Bestätigung von ihm eingefordert.
	 *
	 * @param  boolean         $confirmed    wenn true, beendet die Methode direkt mit true
	 * @param  _WV16_Attribute $attribute    die betreffende Metainformation
	 * @param  int             $newDatatype  die ID des neuen Datentyps
	 * @return boolean                       true, wenn alles i.O. ist, false, wenn ein Formular generiert wurde und der Controller anhalten soll
	 */
	public static function checkCompatibility($confirmed, $attribute, $newDatatype) {
		if ($confirmed) {
			return true;
		}

		list($newParams,) = WV_Datatype::call($newDatatype, 'serializeConfigForm');

		$converter = new _WV_Convert_Manager($attribute->getDatatypeID(), (int) $newDatatype, $attribute->getParams(), $newParams);
		$status    = $converter->checkCompatibility();

		if ($status === true) {
			return true;
		}

		// convertible, messages, affected
		extract($status);

		if ($affected === true) {
			$affected = WV16_Users::getUsersWithAttribute($attribute);
		}
		else {
			$sql      = WV_SQLEx::getInstance();
			$prefix   = WV_SQLEx::getPrefix();
			$where    = str_replace('$$$value_column$$$', 'value', $affected);
			$affected = array();

			$sql->queryEx('SELECT user_id FROM '.$prefix.'wv16_user_values WHERE '.$where);

			foreach ($sql as $row) {
				$affected[] = _WV16_User::getInstance($row['user_id']);
			}
		}

		if (empty($messages)) {
			$messages = array('Der Datentyp hat sich geändert. Eine Konvertierung ist '.($convertible ? 'jedoch <u>automatisiert</u>' : '<u>nicht</u>').' möglich.');
		}

		$datatypeChanged = $attribute->getDatatypeID() !== (int) $newDatatype;
		include _WV16_PATH.'templates/attributes/confirmation.phtml';
		return false;
	}

	public static function getDatatypeWithParams($attribute) {
		if ($attribute instanceof self) {
			return array('datatype' => $attribute->getDatatypeID(), 'params' => $attribute->getParams());
		}

		$attribute = self::getIDForName($attribute);
		return WV_SQLEx::getInstance()->safeFetch('datatype, params', 'wv16_attributes', 'id = ?', $attribute);
	}

	/**
	 * ID ermitteln
	 *
	 * Gibt die ID eines Attributes für dessen Namen zurück.
	 *
	 * @throws WV16_Exception  falls der Name nicht gefunden wurde
	 * @param  string $name    der interne Name der Metainformation
	 * @param  int    $type    der Typ der Metainformation
	 * @return int             die ID
	 */
	public static function getIDForName($name) {
		if (sly_Util_String::isInteger($name)) {
			return (int) $name;
		}

		debug_print_backtrace();

		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.internal.mappings';
		$cacheKey  = sly_Cache::generateKey('attribute', strtolower($name));

		$id = $cache->get($namespace, $cacheKey, -1);

		if ($id > 0) {
			return (int) $id;
		}

		// Wir sortieren nach dem deleted-Status. Auf diese Weise wird, falls es ein
		// altes gelöschtes Attribut mit dem Namen X und ein Live-Attribut mit dem Namen
		// X gibt, das neuere selektiert. Das gelöschte Attribut (bzw. die gelöschten)
		// können dann nur noch über ihre ID im Konstruktur aufgerufen werden.
		// Sind mehrere gelöschte mit dem gleichen Namen vorhanden, wird das letzte
		// (jüngste) mit dem Namen selektiert.

		$sql = WV_SQLEx::getInstance();
		$id  = $sql->safeFetch('id', 'wv16_attributes', 'LOWER(name) = ? ORDER BY deleted ASC, id DESC', strtolower($name));

		if (!$id) {
			throw new WV16_Exception('Das Attribut "'.$name.'" konnte nicht gefunden werden!');
		}

		$cache->set($namespace, $cacheKey, (int) $id);
		return (int) $id;
	}

	/*@{*/

	/**
	 * Getter
	 *
	 * Gibt die entsprechende Eigenschaft ungefiltert zurück.
	 *
	 * @return mixed  die entsprechende Eigenschaft
	 */

	public function getID()          { return $this->id;           }
	public function getName()        { return $this->name;         }
	public function getTitle()       { return $this->title;        }
	public function getPosition()    { return $this->position;     }
	public function getDatatypeID()  { return $this->datatype;     }
	public function getParams()      { return $this->params;       }
	public function getDefault()     { return $this->defaultValue; }
	public function getUserTypes()   { return $this->userTypes;    }
	public function getHelpText()    { return $this->helptext;     }
	public function isVisible()      { return !$this->hidden;      }
	public function isHidden()       { return $this->hidden;       }
	public function isMultilingual() { return false;               }

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

	public function setDatatype($value)     { $this->datatype     = (int) $value; }
	public function setDefaultValue($value) { $this->defaultValue = $value;       }

	public function setName($value) {
		$value = trim($value);

		if (empty($value)) {
			throw new WV16_Exception('Der interne Name darf nicht leer sein.');
		}

		$this->name = $value;
	}

	public function setParams($value) {
		$this->params = $value;
	}

	public function setTitle($value) {
		$value = trim($value);

		if (empty($value)) {
			throw new WV16_Exception('Der Titel darf nicht leer sein.');
		}

		$this->title = $value;
	}

	public function setHelpText($helptext) {
		$this->helptext = trim($helptext);
	}

	public function setHidden($hidden) {
		$this->hidden = (boolean) $hidden;
	}

	public function setUserTypes($value) {
		$this->userTypes = array_unique(array_map('intval', sly_makeArray($value)));
	}

	/*@}*/
}