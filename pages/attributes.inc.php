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

	$position = wv_get('position', 'int');

	try {
		$attribute = _WV16_Attribute::getInstance($id);
		$attribute->shift($position);
	}
	catch (Exception $e) {
		// pass..
	}

	rex_register_extension_point('WV16_ATTRIBUTE_SHIFTED', $attribute);
	WV_Redaxo::clearOutput();
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
	$name      = wv_post('name',     'string');
	$title     = wv_post('title',    'string');
	$helptext  = wv_post('helptext', 'string');
	$datatype  = wv_post('datatype', 'int');
	$hidden    = wv_post('hidden',   'boolean', false);
	$usertypes = wv_postArray('utypes', 'int');

	try {
		if (!WV_Datatype::exists($datatype)) {
			throw new WV16_Exception('Der gewählte Datentyp existiert nicht!');
		}
		
		list($params, $default) = WV_Datatype::call($datatype, 'serializeBackendForm', array(null));
		$attribute = _WV16_Attribute::create($name, $title, $helptext, $datatype, $params, $default, $hidden, $usertypes);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}

	rex_register_extension_point('WV16_ATTRIBUTE_ADDED', $attribute);
	WV_Redaxo::success('Das Attribut wurde erfolgreich gespeichert.');

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
	
	rex_register_extension_point('WV16_ATTRIBUTE_DELETED', $attribute);
	WV_Redaxo::success('Das Attribut wurde gelöscht.');

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

	if (!empty($_POST['delete'])) {
		$func = 'delete';
		++$loop;
		continue;
	}

	$attribute     = null;
	$name          = wv_post('name',  'string');
	$title         = wv_post('title', 'string');
	$helptext      = wv_post('helptext', 'string');
	$datatype      = wv_post('datatype', 'int');
	$hidden        = wv_post('hidden',       'boolean', false);
	$confirmed     = wv_post('confirmed',    'boolean', false);
	$noconversion  = wv_post('noconversion', 'boolean', false);
	$applyDefaults = wv_post('datatype_'.$datatype.'_applydefault', 'boolean', false);
	$usertypes     = wv_postArray('utypes', 'int');

	try {
		if (!WV_Datatype::exists($datatype)) {
			throw new WV16_Exception('Der gewählte Datentyp existiert nicht!');
		}
		
		$attribute = _WV16_Attribute::getInstance($id);

		// VOR dem Update prüfen, ob ein Löschen von Daten notwendig ist. Falls
		// ja, den Benutzer erst fragen, bevor wir die Daten übernehmen.

		if (!_WV16_Attribute::checkCompatibility($confirmed, $attribute, $datatype)) {
			break;
		}

		$attribute->setName($name);
		$attribute->setTitle($title);
		$attribute->setHelpText($helptext);
		$attribute->setHidden($hidden);
		$attribute->setDatatype($datatype);
		$attribute->setUserTypes($usertypes);

		list($params, $default) = WV_Datatype::call($datatype, 'serializeBackendForm', $attribute);

		$attribute->setParams($params);
		$attribute->setDefaultValue($default);

		$attribute->update(!$noconversion, $applyDefaults);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	rex_register_extension_point('WV16_ATTRIBUTE_UPDATED', $attribute);
	WV_Redaxo::success('Das Attribut wurde erfolgreich gespeichert.');

	// kein break;

#===============================================================================
# Vorhandene Attribute anzeigen
#===============================================================================
default:
	
	$search = WV_Table::getSearchParameters('attributes');
	$paging = WV_Table::getPagingParameters('attributes', true, false);
	$where  = 'deleted = 0';
	
	if (!empty($search)) {
		$searchSQL = ' AND (`name` = ? OR `title` = ? OR `params` = ? OR `default_value` = ?)';
		$searchSQL = str_replace('=', 'LIKE', $searchSQL);
		$searchSQL = str_replace('?', '"%'.WV_SQL::escape($search).'%"', $searchSQL);
		
		$where .= $searchSQL;
	}
	
	$attributes = WV16_Users::getAllAttributes($where, 'position', 'asc', $paging['start'], $paging['elements']);
	$total      = WV16_Users::getTotalAttributes($where);
	require _WV16_PATH.'templates/attributes/table.phtml';
}}
