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

$classes = array(
	'_WV16'             => 'internal/class.frontenduser.php',
	'_WV16_Attribute'   => 'internal/class.attribute.php',
	'_WV16_UserType'    => 'internal/class.usertype.php',
	'_WV16_UserValue'   => 'internal/class.uservalue.php',
	'_WV16_Group'       => 'internal/class.group.php',
	'_WV16_DataHandler' => 'internal/class.datahandler.php',
	'_WV16_User'        => 'internal/class.user.php',

	'WV16_Users'     => 'class.users.php',
	'WV16_Mailer'    => 'class.mailer.php',
	'WV16_Exception' => 'class.exception.php',

	'PHPMailerLite' => 'class.phpmailer-lite.php'
);

if (isset($classes[$className])) {
	require_once _WV16_PATH.'classes/'.$classes[$className];
	$className = '';
}
