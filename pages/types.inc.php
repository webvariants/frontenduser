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

$id   = wv_request('id', 'int');
$func = wv_request('func', 'string');
$loop = 1;

while ($loop) { --$loop; switch ($func) {
#===============================================================================
# Benutzertyp verschieben
#===============================================================================
case 'shift':

	$position = wv_get('position', 'int');

	try {
		$attribute = _WV16_Attribute::getInstance($id);
		$attribute->shift($position);
	}
	catch (Exception $e) {
		// pass..
	}

	WV_Redaxo::clearOutput();
	die;

#===============================================================================
# Benutzertyp hinzufügen
#===============================================================================
case 'add':

	$type = null;
	include _WV16_PATH.'templates/types/backend.phtml';
	break;

#===============================================================================
# Benutzertyp speichern
#===============================================================================
case 'do_add':

	$type       = null;
	$name       = wv_post('name',  'string');
	$title      = wv_post('title', 'string');
	$attributes = wv_postArray('attributes', 'int');

	try {
		$type = _WV16_UserType::create($name, $title, $attributes);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}

	WV_Redaxo::success('Der Benutzertyp wurde erfolgreich gespeichert.');

	$func = '';
	++$loop;
	continue;

#===============================================================================
# Benutzertyp löschen
#===============================================================================
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

#===============================================================================
# Benutzertyp bearbeiten
#===============================================================================
case 'edit':

	$type = _WV16_UserType::getInstance($id);
	include _WV16_PATH.'templates/types/backend.phtml';
	break;

#===============================================================================
# Benutzertyp speichern
#===============================================================================
case 'do_edit':

	if (isset($_POST['delete'])) {
		$func = 'delete';
		++$loop;
		continue;
	}

	$confirmed  = wv_post('confirmed', 'boolean', false);
	$type       = null;
	$name       = wv_post('name',  'string');
	$title      = wv_post('title', 'string');
	$attributes = wv_postArray('attributes', 'int');
	
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

	WV_Redaxo::success('Der Benutzertyp wurde erfolgreich gespeichert.');

	// kein break;

#===============================================================================
# Vorhandene Benutzertypen anzeigen
#===============================================================================
default:

	$data = WV16_Users::getAllUserTypes();
	require _WV16_PATH.'templates/types/table.phtml';
} }
