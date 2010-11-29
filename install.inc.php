<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

function _wv16_createSingleLineSetting($namespace, $name, $title, $helptext, $group, $pagename) {
	return _wv16_createSetting($namespace, $name, $title, $helptext, $group, $pagename, 1);
}

function _wv16_createMultiLineSetting($namespace, $name, $title, $helptext, $group, $pagename) {
	return _wv16_createSetting($namespace, $name, $title, $helptext, $group, $pagename, 2);
}

function _wv16_createSetting($namespace, $name, $title, $helptext, $group, $pagename, $datatype) {
	$setting = WV8_Settings::create(
		/*     Namespace */ $namespace,
		/*          Name */ $name,
		/*         Titel */ $title,
		/*     Hilfetext */ $helptext,
		/*      Datentyp */ $datatype,
		/*     Parameter */ '0|65535',
		/*        lokal? */ false,
		/*    Seitenname */ $pagename,
		/*        Gruppe */ $group,
		/* mehrsprachig? */ true
	);

	return $setting;
}

// remove old settings
include dirname(__FILE__).'/uninstall.inc.php';

WV8_Settings::create(
	/*     Namespace */ $namespace,
	/*          Name */ 'validation_article',
	/*         Titel */ 'Validierungsartikel',
	/*     Hilfetext */ 'Dieser Artikel muss die Validierung des Bestätigungscodes (= entsprechendes Modul) für den Benutzer ermöglichen.',
	/*      Datentyp */ 4,
	/*     Parameter */ '1',
	/*        lokal? */ false,
	/*    Seitenname */ $pagename,
	/*        Gruppe */ 'Validierungsartikel',
	/* mehrsprachig? */ true
);

$helptext = 'Verwenden Sie die internen Attributnamen und Rauten (#...#) als Platzhalter, z.B. #LOGIN# oder #FIRSTNAME#.';

$group = 'Ausgehende eMails';
_wv16_createSingleLineSetting($namespace, 'mail_from_name',  'eMail-Name',    '', $group, $pagename);
_wv16_createSingleLineSetting($namespace, 'mail_from_email', 'eMail-Adresse', '', $group, $pagename);

$group = 'eMail-Benachrichtigung bei neuen Benutzern (für den Administrator)';
_wv16_createSingleLineSetting($namespace, 'mail_report_subject', 'Betreff',            $helptext, $group, $pagename);
_wv16_createMultiLineSetting($namespace,  'mail_report_body',    'Inhalt (Template)',  $helptext, $group, $pagename);

$group = 'Bestätigungsaufforderung an den neuen Benutzer';
_wv16_createSingleLineSetting($namespace, 'mail_confirmation_subject', 'Betreff',              $helptext, $group, $pagename);
_wv16_createMultiLineSetting($namespace,  'mail_confirmation_body',    'Inhalt (Template)',    $helptext, $group, $pagename);
_wv16_createSingleLineSetting($namespace, 'mail_confirmation_to',      'Empfänger (Template)', $helptext, $group, $pagename);

$group = 'Benachrichtigung des Benutzers, wenn er im Backend aktiviert wird';
_wv16_createSingleLineSetting($namespace, 'mail_activation_subject', 'Betreff',              $helptext, $group, $pagename);
_wv16_createMultiLineSetting($namespace,  'mail_activation_body',    'Inhalt (Template)',    $helptext, $group, $pagename);
_wv16_createSingleLineSetting($namespace, 'mail_activation_to',      'Empfänger (Template)', $helptext, $group, $pagename);

$group = 'Passwort-vergessen-eMails';
_wv16_createSingleLineSetting($namespace, 'mail_recovery_subject', 'Betreff',              $helptext, $group, $pagename);
_wv16_createMultiLineSetting($namespace,  'mail_recovery_body',    'Inhalt (Template)',    $helptext, $group, $pagename);
_wv16_createSingleLineSetting($namespace, 'mail_recovery_to',      'Empfänger (Template)', $helptext, $group, $pagename);

$group = 'Passwort-vergessen-Anforderungs-eMails';
_wv16_createSingleLineSetting($namespace, 'mail_recoveryrequest_subject', 'Betreff',              $helptext, $group, $pagename);
_wv16_createMultiLineSetting($namespace,  'mail_recoveryrequest_body',    'Inhalt (Template)',    $helptext, $group, $pagename);
_wv16_createSingleLineSetting($namespace, 'mail_recoveryrequest_to',      'Empfänger (Template)', $helptext, $group, $pagename);

$setting = WV8_Settings::create(
	/*     Namespace */ $namespace,
	/*          Name */ 'be_columns',
	/*         Titel */ 'Spalten in Benutzerliste',
	/*     Hilfetext */ 'Wählen Sie, welche Informationen im Backend zusätzlich zum Login angezeigt werden sollen.',
	/*      Datentyp */ 3,
	/*     Parameter */ '1|1_1_0_5|SELECT "___login___", "Login" UNION (SELECT name, title FROM '.WV_SQLEx::getPrefix().'wv16_attributes WHERE 1 ORDER BY title) UNION SELECT "___type___", "Benutzertyp" UNION SELECT "___registered___", "Registrier-Datum"',
	/*        lokal? */ false,
	/*    Seitenname */ $pagename,
	/*        Gruppe */ 'Backend',
	/* mehrsprachig? */ false
);
