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

$required = $REX['ADDON']['status']['developer_utils'] && WV_Redaxo::isRequired('frontenduser');

if ($required && !wv_get('force', 'int', 0)) {
	$required = WV_Redaxo::isRequired('frontenduser');
	$REX['ADDON']['installmsg']['frontenduser'] = 'Es wird von den folgenden AddOns benötigt:<br />'.$required;
}
else {
	$REX['ADDON']['install']['frontenduser'] = 0;
}
