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
	 * Liste aller CSS-Dateien des AddOns.
	 */
	private static $cssFiles = array(
		'*' => array('addons/frontenduser' => 'css/wv16.css')
	);

	/**
	 * AddOn in Extension Points einklinken
	 *
	 * Diese Methode registriert sich bei allen von dem AddOn genutzten
	 * Extension Points. Bis auf OOREDAXO_GET_META_VALUE werden die
	 * Registrierungen nur im Backend vorgenommen.
	 *
	 * @param string $page   die aktuelle Seite im Backend
	 * @param string $mode   der Seitenmodus (z.B. "meta" bei der Seite "content")
	 * @param bool   $force  wenn true, wird sich MetaInfoEx in die PAGE_*-EPs einhängen, auch wenn der User sich gerade nicht auf einer MetaInfoEx-Seite befindet
	 */
	public static function plugin($page, $mode, $force = false) {
		global $REX, $ctype;

		$self = __CLASS__;

		if (!$REX['REDAXO'] && !$force) return;
		
		// HTML-Kopf

		if ( in_array($page, array('frontenduser', 'structure', 'medienpool', 'mediapool', 'content')) || $force ) {
			rex_register_extension('PAGE_HEADER', array($self, 'pageHeader'));
		}

		// Reagieren, falls Artikel gelöscht werden, um unsere Daten aktuell zu halten

		rex_register_extension('ART_DELETED', array($self, 'artDeleted'));

		// Artikel verarbeiten

		if ($page == 'content') {
			rex_register_extension('ART_META_FORM_SECTION', array($self, 'artMetaSectionForm'));
		}

		// Kategorie verarbeiten

		if ($page == 'structure') {
			rex_register_extension('CAT_UPDATED',   array($self, 'catUpdated'));
			rex_register_extension('CAT_FORM_EDIT', array($self, 'catFormEdit'));
			rex_register_extension('CAT_DELETED',   array($self, 'catDeleted'));
		}

		// Medien verarbeiten

		if ($page == 'medienpool' /* 4.1 */ || $page == 'mediapool' /* 4.2 */) {
			rex_register_extension('MEDIA_UPDATED',   array($self, 'mediaUpdated'));
			rex_register_extension('MEDIA_FORM_EDIT', array($self, 'mediaFormEdit'));
			if (rex_post('btn_delete', 'string')) self::mediaDeleted();
		}
	}
	
	public static function sendFiles() {
		$js  = rex_get('js',  'string');
		$css = rex_get('css', 'string');

//		if (!empty($js) && isset(self::$jsFiles[$js])) {
//			rex_send_file(_WV2_PATH.self::$jsFiles[$js], 'text/javascript', 'frontend');
//			exit;
//		}

		foreach ( self::$cssFiles as $version => $files ) {
			if (!empty($css) && isset($files[$css])) {
				rex_send_file(_WV16_PATH.$files[$css], 'text/css', 'frontend');
				exit;
			}
		}
	}

	/*
	   ************************************************************
	     Extension Points - Allgemein
	   ************************************************************
	*/

	/**
	 * Handler für PAGE_HEADER
	 *
	 * Generiert die HTML-Codes zur Einbindung der CSS- und JavaScript-Dateien.
	 *
	 * @param  array $params  die von Redaxo übergebenen Parameter
	 * @return string         das von Redaxo übergebende Subject plus der zusätzlichen Angaben
	 */
	public static function pageHeader($params) {
		$files = array();
		foreach (self::$cssFiles as $version => $cssFiles) {
			if ( $version == '*' || $version == WV2::getRedaxoVersion() ) {
				foreach ( $cssFiles as $code => $filename) {
					$files[] = '<link rel="stylesheet" type="text/css" href="index.php?css='.$code.'" />';
				}
			}
		}
//		foreach (self::$jsFiles as $code => $filename) {
//			$files[] = '<script src="index.php?js='.$code.'" type="text/javascript"></script>';
//		}
		return $params['subject'].'  '.implode("\n  ", $files)."\n";
	}

	/*
	   ************************************************************
	     Extension Points - Metadaten für Artikel
	   ************************************************************
	*/

	/**
	 * Handler für ART_DELETED
	 * 
	 * Löscht alle Metadaten für den gelöschten Artikel.
	 * 
	 * @param array $params  die von Redaxo übergebenen Parameter
	 */
	public static function artDeleted($params) {
		list($articleID) = array_values($params);
		WV_SQL::getInstance()->query('DELETE FROM #_wv16_rights WHERE object_id = '.intval($articleID).' AND object_type = '._WV16::TYPE_ARTICLE, '#_');
	}

	/*
	   ************************************************************
	     Extension Points - Metadaten für Artikel (Meta-Seite)
	   ************************************************************
	*/

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
		
		$enableAccess    = (bool) rex_post('frontenduser', 'int', 0);
		list($articleID) = array_values($params);
		$sql             = WV_SQL::getInstance();
		
		// Rechte entfernen, um später stupide INSERTs ausführen zu können.
		
		$sql->query('DELETE FROM #_wv16_rights WHERE object_id = '.intval($articleID).' AND object_type = '._WV16::TYPE_ARTICLE, '#_');
		
		// Explizite Rechte wurden deaktiviert? Dann fügen wir keinen neuen hinzu.
		
		if (!$enableAccess) return;
		
		// Rechte holen und abspeichern
		
		foreach (WV16_Users::getAllGroups() as $group) {
			$formName  = md5($group->getName());
			$privilege = rex_post($formName, 'int', 0) ? 1 : 0;
			
			$sql->query('INSERT INTO #_wv16_rights (group_id,object_id,object_type,privilege) '.
				'VALUES ('.$group->getID().','.intval($articleID).','._WV16::TYPE_ARTICLE.','.$privilege.')', '#_');
		}
		
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
		
		$enableAccess = (bool) rex_post('frontenduser', 'int', 0);
		$categoryID   = rex_post('edit_id', 'int'); // $params['category'] ist ein rex_sql-Objekt
		$sql          = WV_SQL::getInstance();
		
		// Rechte entfernen, um später stupide INSERTs ausführen zu können.
		
		$sql->query('DELETE FROM #_wv16_rights WHERE object_id = '.intval($categoryID).' AND object_type = '._WV16::TYPE_CATEGORY, '#_');
		
		// Explizite Rechte wurden deaktiviert? Dann fügen wir keinen neuen hinzu.
		
		if (!$enableAccess) return;
		
		// Rechte holen und abspeichern
		
		foreach (WV16_Users::getAllGroups() as $group) {
			$formName  = md5($group->getName());
			$privilege = rex_post($formName, 'int', 0) ? 1 : 0;
			
			$sql->query('INSERT INTO #_wv16_rights (group_id,object_id,object_type,privilege) '.
				'VALUES ('.$group->getID().','.intval($categoryID).','._WV16::TYPE_CATEGORY.','.$privilege.')', '#_');
		}
		
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
		WV_SQL::getInstance()->query('DELETE FROM #_wv16_rights WHERE object_id = '.intval($categoryID).' AND object_type = '._WV16::TYPE_CATEGORY, '#_');
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
		$enableAccess = (bool) rex_post('frontenduser', 'int', 0);
		$mediumID     = rex_post('file_id', 'int');
		$sql          = WV_SQL::getInstance();
		
		// Rechte entfernen, um später stupide INSERTs ausführen zu können.
		
		$sql->query('DELETE FROM #_wv16_rights WHERE object_id = '.intval($mediumID).' AND object_type = '._WV16::TYPE_MEDIUM, '#_');
		
		// Explizite Rechte wurden deaktiviert? Dann fügen wir keinen neuen hinzu.
		
		if (!$enableAccess) return;
		
		// Rechte holen und abspeichern
		
		foreach (WV16_Users::getAllGroups() as $group) {
			$formName  = md5($group->getName());
			$privilege = rex_post($formName, 'int', 0) ? 1 : 0;
			
			$sql->query('INSERT INTO #_wv16_rights (group_id,object_id,object_type,privilege) '.
				'VALUES ('.$group->getID().','.intval($mediumID).','._WV16::TYPE_MEDIUM.','.$privilege.')', '#_');
		}
	}

	/**
	 * Handler für MEDIA_DELETED
	 * 
	 * Entfernt alle Metadaten für die gelöschte Datei.
	 */
	public static function mediaDeleted() {
		$mediumID = rex_post('file_id', 'int');
		WV_SQL::getInstance()->query('DELETE FROM #_wv16_rights WHERE object_id = '.intval($mediumID).' AND object_type = '._WV16::TYPE_MEDIUM, '#_');
	}
}
