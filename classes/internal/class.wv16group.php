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

class _WV16_Group {
	const GROUP_UNCONFIRMED = 1;
	const GROUP_CONFIRMED   = 2;
	const GROUP_ACTIVATED   = 3;
	const DEFAULT_GROUP     = self::GROUP_UNCONFIRMED;
	
	private $id;
	private $name;
	private $title;
	private $internal;
	private $parentID;
	
	private static $instances = array();
	
	public static function getInstance($groupID) {
		$groupID = intval($groupID);
		if (empty(self::$instances[$groupID])) self::$instances[$groupID] = new self($groupID);
		return self::$instances[$groupID];
	}
	
	public static function exists($groupID) {
		$groupID = intval($groupID);
		return WV_SQL::getInstance()->count('wv16_groups', 'id = '.$groupID) == 1;
	}
	
	private function __construct($id) {
		$sql = WV_SQL::getInstance();
		$data = $sql->fetch('*', 'wv16_groups', 'id = '.$id);
		
		if (empty($data)) throw new Exception('Die Gruppe #'.$id.' konnte nicht gefunden werden!');
		
		$this->id       = (int) $data['id'];
		$this->name     = $data['name'];
		$this->title    = $data['title'];
		$this->internal = (bool) $data['internal'];
		$this->parentID = (int) $data['parent_id'];
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getID() {
		return $this->id;
	}
	
	public function getTitle() {
		return $this->title;
	}
	
	public function canAccess($object, $objectType = null) { /* Typ ist nur nötig, wenn die Objekt-ID nicht eindeutig ist */
		list($objectID, $objectType) = _WV16::identifyObject($object, $objectType);
		$sql = WV_SQL::getInstance();
		
		// Die Berechtigung für dieses Objekt allein abrufen (explizite Erlaubnis?)
		
		$privilege = $sql->fetch('privilege', 'wv16_rights', 'object_id = '.$objectID.' AND object_type = '.$objectType.' AND group_id = '.$this->id);
		if ($privilege) return true;
		
		// Verboten? Vielleicht gibt es für dieses Objekt einfach keine expliziten Rechte.
		
		$privileges = $sql->count('wv16_rights', 'object_id = '.$objectID.' AND object_type = '.$objectType);
		if (!empty($privileges)) return false; // es gibt also durchaus Rechte für dieses Objekt.
		
		// In diesem Fall wäre der Zugriff erlaubt, wenn das Elternelement (soweit vorhanden)
		// den Zugriff erlaubt. Medien vererben keine Rechte.
		
		if ($objectType == _WV16::TYPE_MEDIUM) return false;
		
		// Prüfen, ob es sich, wenn wir einen Artikel haben, es sich
		// gleichzeitig auch um eine Kategorie handelt.
		
		$isStartpage = $sql->fetch('startpage', 'article', 'id = '.$objectID) && $objectType == _WV16::TYPE_ARTICLE;
		
		// Artikel und Kategorien hingegen schon. Also brauchen wir jetzt das
		// Elternelement. Bei einem Artikel ist das die ihn beinhaltende Kategorie,
		// bei einer Kategorie die Elternkategorie.
		// Wenn es sich um eine Startseite einer Kategorie handelt, ist die
		// Elternkategorie logischerweise direkt "der Artikel selbst".

		$parentCategory = $isStartpage ? $objectID : $sql->fetch('re_id', 'article', 'id = '.$objectID);
		if ($parentCategory == 0) return true; // Artikel/Kategorie der obersten Ebene, da generell alle Objekte erlaubt sind, hören wir hier auf.
		return $this->canAccess(intval($parentCategory), _WV16::TYPE_CATEGORY);
	}
}
