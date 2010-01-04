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

WV_Redaxo::addJavaScriptFile('developer_utils/js/jquery.tablednd.js');
require $REX['INCLUDE_PATH'].'/layout/top.php';

$subpages = array(
	array('',           'Benutzer'),
//	array('groups',     'Gruppen'),
	array('types',      'Benutzertypen'),
	array('attributes', 'Attribute')
);

$subpageFiles = array(
	''           => 'users',
//	'groups'     => 'groups',
	'types'      => 'types',
	'attributes' => 'attributes'
);

rex_title('Benutzerverwaltung', $subpages);

$subpage = rex_request('subpage', 'string');

if (isset($subpageFiles[$subpage])) {
	require _WV16_PATH.'pages/'.$subpageFiles[$subpage].'.inc.php';
}

require $REX['INCLUDE_PATH'].'/layout/bottom.php';
