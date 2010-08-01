<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

$filename = $_REQUEST['wv16_file'];
$media    = OOMedia::getMediaByFilename($filename);

if (OOMedia::isValid($media)) {
	if (!WV16_Users::isProtected($media) || (WV16_Users::isLoggedIn() && WV16_Users::getCurrentUser()->canAccess($media))) {
		header('Content-Type: '.$media->getType());
		readfile('files/'.$media->getFileName());
	}
	else {
		header('HTTP/1.1 403 Forbidden');
	}
}
else {
	header('HTTP/1.1 404 Not Found');
}

exit;
