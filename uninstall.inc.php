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

// ==== EINSTELLUNGEN LÖSCHEN ====================================

$pagename  = 'translate:frontenduser_title';
$namespace = 'frontenduser';
$settings  = array(
	'validation_article',
	'mail_from_name', 'mail_from_email',
	'mail_report_subject', 'mail_report_body',
	'mail_confirmation_to', 'mail_confirmation_subject', 'mail_confirmation_body',
	'mail_activation_to', 'mail_activation_subject', 'mail_activation_body',
	'mail_recovery_to', 'mail_recovery_subject', 'mail_recovery_body',
	'mail_recoveryrequest_to', 'mail_recoveryrequest_subject', 'mail_recoveryrequest_body'
);

foreach ($settings as $setting) {
	WV8_Settings::deleteIfExists($namespace, $setting);
}
