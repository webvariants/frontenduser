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

if (empty($REX['ADDON']['status']['developer_utils'])) {
	$REX['ADDON']['installmsg']['frontenduser'] = 'Bitte installieren &amp; aktivieren Sie vor der Installation das Developer Utils-AddOn.';
}
else {
	$REX['ADDON']['install']['frontenduser'] = 1;
}
