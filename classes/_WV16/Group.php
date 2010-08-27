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

	protected $id;
	protected $name;
	protected $title;
	protected $internal;
	protected $parentID;

	private static $instances = array();

	public static function getInstance($groupID) {
		$groupID = (int) $groupID;

		if (isset(self::$instances[$groupID])) {
			return self::$instances[$groupID];
		}

		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.internal.groups';
		$instance  = $cache->get($namespace, $groupID);

		if (!$instance) {
			if ($cache->lock($namespace, $groupID)) {
				try {
					$instance = new self($groupID);
					$cache->set($namespace, $groupID, $instance);
					$cache->unlock($namespace, $groupID);
				}
				catch (Exception $e) {
					$cache->unlock($namespace, $groupID);
					throw $e;
				}
			}
			else {
				$instance = $cache->waitForObject($namespace, $groupID);

				if (!$instance) {
					$instance = new self($groupID);
				}
			}
		}

		self::$instances[$groupID] = $instance;
		return self::$instances[$groupID];
	}

	private function __construct($id) {
		$sql = WV_SQLEx::getInstance();
		$data = $sql->saveFetch('*', 'wv16_groups', 'id = ?', $id);

		if (empty($data)) {
			throw new WV16_Exception('Die Gruppe #'.$id.' konnte nicht gefunden werden!');
		}

		$this->id       = (int) $data['id'];
		$this->name     = $data['name'];
		$this->title    = $data['title'];
		$this->internal = (bool) $data['internal'];
		$this->parentID = (int) $data['parent_id'];
	}

	public static function getIDForName($group) {
		if (WV_String::isInteger($group)) {
			return (int) $group;
		}

		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.internal.groups';
		$cacheKey  = WV_Cache::generateKey('id_for_name', $group);

		$id = $cache->get($namespace, $cacheKey, -1);

		if ($id < 0) {
			$sql = WV_SQLEx::getInstance();
			$id  = $sql->saveFetch('id', 'wv16_groups', 'name = ?', $group);
			$id  = $id === false ? -1 : (int) $id;
			$cache->set($namespace, $cacheKey, $id);
		}

		return (int) $id;
	}

	public static function exists($group) {
		$groupID = self::getIDForName($group);
		return $groupID > 0;
	}

	public function getName()  { return $this->name;  }
	public function getID()    { return $this->id;    }
	public function getTitle() { return $this->title; }

	public function canAccess($object, $objectType = null) { /* Typ ist nur nötig, wenn die Objekt-ID nicht eindeutig ist */
		list($objectID, $objectType) = _WV16_FrontendUser::identifyObject($object, $objectType);

		$cache     = WV_DeveloperUtils::getCache();
		$namespace = 'frontenduser.rights';
		$cacheKey  = WV_Cache::generateKey('can_access', $this->id, $objectID, $objectType);
		$canAccess = $cache->get($namespace, $cacheKey, null);

		if (is_bool($canAccess)) {
			return $canAccess;
		}

		$sql = WV_SQLEx::getInstance();

		// Die Berechtigung für dieses Objekt allein abrufen (explizite Erlaubnis?)

		$privilege = $sql->saveFetch('privilege', 'wv16_rights', 'object_id = ? AND object_type = ? AND group_id = ?', array($objectID, $objectType, $this->id));

		if ($privilege) {
			$cache->set($namespace, $cacheKey, true);
			return true;
		}

		// Verboten? Vielleicht gibt es für dieses Objekt einfach keine expliziten Rechte.

		$privileges = $sql->count('wv16_rights', 'object_id = ? AND object_type = ?', array($objectID, $objectType));

		if (!empty($privileges)) {
			$cache->set($namespace, $cacheKey, false);
			return false; // es gibt also durchaus Rechte für dieses Objekt.
		}

		// In diesem Fall wäre der Zugriff erlaubt, wenn das Elternelement (soweit vorhanden)
		// den Zugriff erlaubt. Medien vererben keine Rechte.

		if ($objectType == _WV16_FrontendUser::TYPE_MEDIUM) {
			$cache->set($namespace, $cacheKey, false);
			return false;
		}

		// Prüfen, ob es sich, wenn wir einen Artikel haben, es sich
		// gleichzeitig auch um eine Kategorie handelt.

		$isStartpage = $sql->saveFetch('startpage', 'article', 'id = ?', $objectID) && $objectType == _WV16_FrontendUser::TYPE_ARTICLE;

		// Artikel und Kategorien hingegen schon. Also brauchen wir jetzt das
		// Elternelement. Bei einem Artikel ist das die ihn beinhaltende Kategorie,
		// bei einer Kategorie die Elternkategorie.
		// Wenn es sich um eine Startseite einer Kategorie handelt, ist die
		// Elternkategorie logischerweise direkt "der Artikel selbst".

		$parentCategory = $isStartpage ? $objectID : $sql->saveFetch('re_id', 'article', 'id = ?', $objectID);

		if ($parentCategory == 0) {
			$cache->set($namespace, $cacheKey, true);
			return true; // Artikel/Kategorie der obersten Ebene, da generell alle Objekte erlaubt sind, hören wir hier auf.
		}

		return $this->canAccess((int) $parentCategory, _WV16_FrontendUser::TYPE_CATEGORY);
	}
}
