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

class _WV16_Extensions {
	/**
	 * AddOn in Extension Points einklinken
	 *
	 * Diese Methode registriert sich bei allen von dem AddOn genutzten
	 * Extension Points. Bis auf OOREDAXO_GET_META_VALUE werden die
	 * Registrierungen nur im Backend vorgenommen.
	 */
	public static function plugin($params) {
		$self       = __CLASS__;
		$page       = $params['subject'];
		$dispatcher = sly_Core::dispatcher();

		// HTML-Kopf

		if (in_array($page, array('frontenduser', 'structure', 'mediapool', 'content'))) {
			$layout = sly_Core::getLayout();
			$layout->addCSSFile('../data/dyn/public/frontenduser/css/wv16.css');
			$layout->addJavaScriptFile('../data/dyn/public/frontenduser/js/frontenduser.min.js');
		}

		// Reagieren, falls Artikel gelöscht werden, um unsere Daten aktuell zu halten

		$dispatcher->register('ART_DELETED', array($self, 'artDeleted'));

		switch ($page) {
			case 'content':
//				$dispatcher->register('ART_META_FORM_SECTION', array($self, 'artMetaSectionForm'));
				break;

			case 'structure':
				$dispatcher->register('CAT_UPDATED',   array($self, 'catUpdated'));
//				$dispatcher->register('CAT_FORM_EDIT', array($self, 'catFormEdit'));
				$dispatcher->register('CAT_DELETED',   array($self, 'catDeleted'));
				break;

			case 'mediapool':
				$dispatcher->register('MEDIA_UPDATED',   array($self, 'mediaUpdated'));
//				$dispatcher->register('MEDIA_FORM_EDIT', array($self, 'mediaFormEdit'));
				if (sly_post('btn_delete', 'string')) self::mediaDeleted();
		}
	}

	/**
	 * Handler für ART_DELETED
	 *
	 * Löscht alle Metadaten für den gelöschten Artikel.
	 *
	 * @param array $params  die von Redaxo übergebenen Parameter
	 */
	public static function artDeleted($params) {
		list($articleID) = array_values($params);
		self::removeRights($articleID, _WV16_FrontendUser::TYPE_ARTICLE);
	}

	/**
	 * Rechte speichern
	 *
	 * Speichert die Rechte aus dem abgeschickten Formular. Wird in Ermangelung
	 * eines passenden Extension-Points beim Erzeugen des Formulars aufgerufen
	 * und reagiert nur, wenn ein passendes Formular abgeschickt wurde.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         die Fehlermeldung oder das Subject
	 */
	public static function artUpdated($params) {
		if (!isset($_POST['saverights'])) return;

		list($articleID) = array_values($params);
		self::objectUpdated($articleID, _WV16_FrontendUser::TYPE_ARTICLE);

		return $params['subject'];
	}

	/**
	 * Handler für ART_META_FORM
	 *
	 * Erzeugt das Frontend-Fomular und hängt es an das Subject an.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         das Subject inkl. des Formulars
	 */
	public static function artMetaSectionForm($params) {
		// Der Extension-Point ART_META_UPDATED ist für uns leider nicht passend,
		// da wir dort den Namen des Artikels mitliefern müssten und dann auch das
		// Speichern von Metadaten anstoßen würden. Daher reagieren wir einfach
		// in diesem EP auf ein evtl. abgeschickte Formular (artUpdated macht
		// nichts, wenn kein abgeschicktes Formular erkannt wurde).
		self::artUpdated($params);

		list($articleID) = array_values($params);

		ob_start();
		include _WV16_PATH.'templates/articleext.phtml';
		$content = ob_get_contents();
		ob_end_clean();

		return $params['subject'].$content;
	}

	/*
	   ************************************************************
	     Extension Points - Metadaten für Kategorien
	   ************************************************************
	*/

	/**
	 * Handler für CAT_FORM_EDIT
	 *
	 * Erzeugt das Frontend-Formular für Kategorien.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         das Subject inkl. des Formulars
	 */
	public static function catFormEdit($params) {
		list($categoryID) = array_values($params);

		ob_start();
		include _WV16_PATH.'templates/categoryext.phtml';
		$content = ob_get_contents();
		ob_end_clean();

		return $params['subject'].$content;
	}

	/**
	 * Handler für CAT_UPDATED
	 *
	 * Serialisiert das Frontend-Formular und gibt ihm Fehlerfalle die
	 * Fehlermeldung, sonst das Subject zurück.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         das Subject oder die Fehlermeldung
	 */
	public static function catUpdated($params) {
		if (!isset($_POST['saverights'])) return;

		$categoryID = sly_post('edit_id', 'int');
		self::objectUpdated($categoryID, _WV16_FrontendUser::TYPE_CATEGORY);

		return $params['subject'];
	}

	/**
	 * Handler für CAT_DELETED
	 *
	 * Entfernt alle Metadaten für die gelöschte Kategorie.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 */
	public static function catDeleted($params) {
		list($categoryID) = array_values($params);
		self::removeRights($categoryID, _WV16_FrontendUser::TYPE_CATEGORY);
	}

	/*
	   ************************************************************
	     Extension Points - Metadaten für Kategorien
	   ************************************************************
	*/

	/**
	 * Handler für MEDIA_FORM_EDIT
	 *
	 * Erzeugt das Frontend-Formular für Medien.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         das Subject inkl. des Formulars
	 */
	public static function mediaFormEdit($params) {
		list($mediumID) = array_values($params);
		$mediumID = intval($mediumID);

		ob_start();
		include _WV16_PATH.'templates/mediumext.phtml';
		$content = ob_get_contents();
		ob_end_clean();

		return str_replace('<!-- ADDITIONAL_FORMS -->', $content.'<!-- ADDITIONAL_FORMS -->', $params['subject']);
	}

	/**
	 * Handler für MEDIA_UPDATED
	 *
	 * Serialisiert das Frontend-Formular und gibt ihm Fehlerfalle die
	 * Fehlermeldung, sonst das Subject zurück.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         das Subject oder die Fehlermeldung
	 */
	public static function mediaUpdated($params) {
		$mediumID = sly_post('file_id', 'int');
		self::objectUpdated($mediumID, _WV16_FrontendUser::TYPE_MEDIUM);
	}

	/**
	 * Handler für MEDIA_DELETED
	 *
	 * Entfernt alle Metadaten für die gelöschte Datei.
	 */
	public static function mediaDeleted() {
		$mediumID = sly_post('file_id', 'int');
		self::removeRights($mediumID, _WV16_FrontendUser::TYPE_MEDIUM);
	}

	private static function objectUpdated($objectID, $type) {
		$enableAccess = sly_post('frontenduser', 'boolean', false);

		// Rechte entfernen, um später stupide INSERTs ausführen zu können.
		self::removeRights($objectID, $type);

		// Explizite Rechte wurden deaktiviert? Dann fügen wir keinen neuen hinzu.
		if (!$enableAccess) return;

		// Rechte holen und abspeichern
		self::storeRights($objectID, $type);
	}

	private static function removeRights($objectID, $type) {
		WV_SQLEx::getInstance()->queryEx('DELETE FROM ~wv16_rights WHERE object_id = ? AND object_type = ?', array($objectID, $type), '#_');
	}

	private static function storeRights($objectID, $type) {
		$sql = WV_SQLEx::getInstance();

		foreach (WV16_Users::getAllGroups() as $group) {
			$formName  = md5($group->getName());
			$privilege = sly_post($formName, 'int', 0) ? 1 : 0;

			$sql->queryEx(
				'INSERT INTO ~wv16_rights (group_id,object_id,object_type,privilege) VALUES (?,?,?,?)',
				array($group->getID(), $objectID, $type, $privilege), '#_'
			);
		}
	}
}
