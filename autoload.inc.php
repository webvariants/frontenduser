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

function _wv16_autoload($params) {
	$className = $params['subject'];
	$classes   = array(
		'_WV16_FrontendUser' => '_WV16/FrontendUser.php',
		'_WV16_Attribute'    => '_WV16/Attribute.php',
		'_WV16_UserType'     => '_WV16/UserType.php',
		'_WV16_UserValue'    => '_WV16/UserValue.php',
		'_WV16_Group'        => '_WV16/Group.php',
		'_WV16_DataHandler'  => '_WV16/DataHandler.php',
		'_WV16_User'         => '_WV16/User.php',

		'WV16_User'      => 'WV16/User.php',
		'WV16_Users'     => 'WV16/Users.php',
		'WV16_Mailer'    => 'WV16/Mailer.php',
		'WV16_Exception' => 'WV16/Exception.php',

		'PHPMailerLite' => 'PHPMailerLite.php'
	);

	if (isset($classes[$className])) {
		require_once _WV16_PATH.'classes/'.$classes[$className];
		return '';
	}

	return $className;
}

rex_register_extension('__AUTOLOAD', '_wv16_autoload');
require_once _WV16_PATH.'classes/_WV16/Extensions.php';
