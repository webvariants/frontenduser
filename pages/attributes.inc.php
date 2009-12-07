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
# Attribut verschieben
#===============================================================================
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

#===============================================================================
# Attribut hinzufügen
#===============================================================================
case 'add':

	$attribute = null;
	include _WV16_PATH.'templates/attributes/backend.phtml';
	break;

#===============================================================================
# Attribut speichern
#===============================================================================
case 'do_add':

	$attribute = null;
	$name      = stripslashes(rex_post('name',  'string'));
	$title     = stripslashes(rex_post('title', 'string'));
	$datatype  = rex_post('datatype', 'int');
	$usertypes = array();

	if (isset($_POST['utypes']) && is_array($_POST['utypes'])) {
		$usertypes = array_map('intval', $_POST['utypes']);
	}

	try {
		if (!WV2::datatypeExists($datatype)) throw new Exception('Der gewählte Datentyp existiert nicht!');
		list($params,$defaultOption) = _WV2::callForDatatype($datatype, 'serializeBackendForm', null);
		$attribute = _WV16_Attribute::create($name, $title, $datatype, $params, $defaultOption, $usertypes);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}

	WV2::success('Das Attribut wurde erfolgreich gespeichert.');

	if (isset($_POST['apply'])) {
		$id   = $attribute->getID();
		$func = 'edit';
		++$loop;
		continue;
	}

	$func = '';
	++$loop;
	continue;

#===============================================================================
# Attribut löschen
#===============================================================================
case 'delete':

	try {
		$attribute = _WV16_Attribute::getInstance($id);
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
# Attribut bearbeiten
#===============================================================================
case 'edit':

	$attribute = _WV16_Attribute::getInstance($id);
	include _WV16_PATH.'templates/attributes/backend.phtml';
	break;

#===============================================================================
# Attribut speichern
#===============================================================================
case 'do_edit':

	if (isset($_POST['delete'])) {
		$func = 'delete';
		++$loop;
		continue;
	}

	$attribute     = null;
	$name          = stripslashes(rex_post('name',  'string'));
	$title         = stripslashes(rex_post('title', 'string'));
	$datatype      = rex_post('datatype', 'int');
	$confirmed     = (bool) rex_post('confirmed',    'int', 0);
	$noconversion  = (bool) rex_post('noconversion', 'int', 0);
	$usertypes     = array();
	$applyDefaults = (bool) rex_post('datatype_'.$datatype.'_applydefault', 'int', 0);

	if (isset($_POST['utypes']) && is_array($_POST['utypes'])) {
		$usertypes = array_map('intval', $_POST['utypes']);
	}

	try {
		if (!WV2::datatypeExists($datatype)) throw new Exception('Der gewählte Datentyp existiert nicht!');
		
		$attribute = _WV16_Attribute::getInstance($id);

		// VOR dem Update prüfen, ob ein Löschen von Daten notwendig ist. Falls
		// ja, den Benutzer erst fragen, bevor wir die Daten übernehmen.

		if (!_WV16_Attribute::checkCompatibility($confirmed, $attribute, $datatype)) break;

		$attribute->setName($name);
		$attribute->setTitle($title);
		$attribute->setDatatype($datatype);
		$attribute->setUserTypes($usertypes);

		list($params, $defaultOption) = _WV2::callForDatatype($datatype, 'serializeBackendForm', $attribute);

		$attribute->setParams($params);
		$attribute->setDefaultValue($defaultOption);

		$attribute->update(!$noconversion, $applyDefaults);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	WV2::success('Das Attribut wurde erfolgreich gespeichert.');

	if (isset($_POST['apply'])) {
		$func = 'edit';
		++$loop;
		continue;
	}

	// kein break;

#===============================================================================
# Vorhandene Attribute anzeigen
#===============================================================================
default:

	$data = WV16_Users::getAttributesForUserType(-1);
	require _WV16_PATH.'templates/attributes/table.phtml';
} }
