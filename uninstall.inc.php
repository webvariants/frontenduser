<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

// ==== EINSTELLUNGEN LÖSCHEN ====================================

$pagename  = 'translate:frontenduser_title';
$namespace = 'frontenduser';
$settings  = array(
	'validation_article', 'recovery_article',
	'mail_from_name', 'mail_from_email',
	'mail_report_subject', 'mail_report_body',
	'mail_confirmation_to', 'mail_confirmation_subject', 'mail_confirmation_body',
	'mail_activation_to', 'mail_activation_subject', 'mail_activation_body',
	'mail_recovery_to', 'mail_recovery_subject', 'mail_recovery_body',
	'mail_recoveryrequest_to', 'mail_recoveryrequest_subject', 'mail_recoveryrequest_body',
	'be_columns'
);

foreach ($settings as $setting) {
	WV8_Settings::deleteIfExists($namespace, $setting);
}
