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

switch (rex_request('func', 'string')) {
#===========================================================
# Einstellungen speichern
#===========================================================
case 'save':
	
	WV16_Users::setConfig('login_article',           rex_post('login_article', 'int', 0));
	WV16_Users::setConfig('register_article',        rex_post('register_article', 'int', 0));
	WV16_Users::setConfig('mail_validation_article', rex_post('mail_validation_article', 'int', 0));
	WV16_Users::setConfig('access_denied_article',   rex_post('access_denied_article', 'int', 0));
	WV16_Users::setConfig('password_forgotten_article',   rex_post('password_forgotten_article', 'int', 0));
	
	WV16_Users::setConfig('admin_name',   rex_post('admin_name', 'string', ''));
	WV16_Users::setConfig('admin_mail',   rex_post('admin_mail', 'string', ''));
	
	foreach ($REX['CLANG'] as $id => $name) {
		WV16_Users::setConfig('confirmation_subject_'.$id, rex_post('confirmation_subject_'.$id, 'string', ''));
		WV16_Users::setConfig('confirmation_body_'.$id, rex_post('confirmation_body_'.$id, 'string', ''));
		
		WV16_Users::setConfig('activation_subject_'.$id, rex_post('activation_subject_'.$id, 'string', ''));
		WV16_Users::setConfig('activation_body_'.$id, rex_post('activation_body_'.$id, 'string', ''));
		
		WV16_Users::setConfig('password_recovery_subject_'.$id, rex_post('password_recovery_subject_'.$id, 'string', ''));
		WV16_Users::setConfig('password_recovery_body_'.$id, rex_post('password_recovery_body_'.$id, 'string', ''));
	}

	// kein break;

#===========================================================
# Einstellungen anzeigen
#===========================================================
default:

	require _WV16_PATH.'templates/settings.phtml';
}
