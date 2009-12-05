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

$id   = rex_request('id', 'int');
$func = rex_request('func', 'string');
$loop = 1;

while ($loop) { --$loop; switch ($func) {
#===========================================================
# Benutzertyp verschieben
#===========================================================
case 'shift':

	$position = rex_get('position', 'int');

	try {
		$attribute = _WV16_Attribute::getInstance($id);
		$attribute->shift($position);
	}
	catch (Exception $e) {
		// pass..
	}

	while (ob_get_level()) ob_end_clean();
	die;

#===========================================================
# Benutzertyp hinzufügen
#===========================================================
case 'add':

	$type = null;
	include _WV16_PATH.'templates/types/backend.phtml';
	break;

#===========================================================
# Benutzertyp speichern
#===========================================================
case 'do_add':

	$type       = null;
	$name       = stripslashes(rex_request('name',  'string'));
	$title      = stripslashes(rex_request('title', 'string'));
	$attributes = array();

	if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
		$attributes = array_map('intval', $_POST['attributes']);
	}

	try {
		$type = _WV16_UserType::create($name, $title, $attributes);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}

	WV2::success('Der Benutzertyp wurde erfolgreich gespeichert.');

	if (isset($_POST['apply'])) {
		$id   = $type->getID();
		$func = 'edit';
		++$loop;
		continue;
	}

	$func = '';
	++$loop;
	continue;

#===========================================================
# Benutzertyp löschen
#===========================================================
case 'delete':

	try {
		$attribute = _WV16_UserType::getInstance($id);
		$attribute->delete();
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	$func = '';
	++$loop;
	continue;

#===========================================================
# Benutzertyp bearbeiten
#===========================================================
case 'edit':

	$type = _WV16_UserType::getInstance($id);
	include _WV16_PATH.'templates/types/backend.phtml';
	break;

#===========================================================
# Benutzertyp speichern
#===========================================================
case 'do_edit':

	if (isset($_POST['delete'])) {
		$func = 'delete';
		++$loop;
		continue;
	}

	$confirmed  = (bool) rex_post('confirmed', 'int', 0);
	$type       = null;
	$name       = stripslashes(rex_request('name',  'string'));
	$title      = stripslashes(rex_request('title', 'string'));
	$attributes = array();

	if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
		$attributes = array_map('intval', $_POST['attributes']);
	}
	
	try {
		$type = _WV16_UserType::getInstance($id);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	/*
	if (!$confirmed) {
		$before = WV2_MetaProvider::getMetaInfosForArticleType($id);
		foreach ( $before as $idx => $info ) $before[$idx] = $info->getId();
		$infosToDelete = array_diff($before, $metainfos);
		foreach ( $infosToDelete as $idx => $id ) $infosToDelete[$idx] = new _WV2_MetaInfo($id, WV2::TYPE_ARTICLE);

		unset($before);

		if (!empty($infosToDelete)) {
			include _WV2_PATH.'templates/articles/type_confirmation.phtml';
			break;
		}
	}
	*/

	try {
		$type->setName($name);
		$type->setTitle($title);
		$type->setAttributes($attributes);
		$type->update();
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	WV2::success('Der Benutzertyp wurde erfolgreich gespeichert.');

	if (isset($_POST['apply'])) {
		$func = 'edit';
		++$loop;
		continue;
	}

	// kein break;

#===========================================================
# Vorhandene Benutzertypen anzeigen
#===========================================================
default:

	$data = WV16_Users::getAllUserTypes();
	require _WV16_PATH.'templates/types/table.phtml';
} }
