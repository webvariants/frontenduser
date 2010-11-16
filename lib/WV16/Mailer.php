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

abstract class WV16_Mailer {
	public static function sendConfirmationRequest(_WV16_User $user, $parameterName = 'confirm') {
		// Code holen

		$code = $user->getConfirmationCode();

		if (empty($code)) {
			// Bestätigungscode erzeugen und speichern

			$code = WV16_Users::generateConfirmationCode($email);
			$user->setConfirmationCode($code);
			$user->update();
		}

		// Artikel finden

		$confirmArticle = OOArticle::getArticleById(WV16_Users::getConfig('validation_article'));

		if ($confirmArticle) {
			$params = array($parameterName => $code);
			$link   = WV_Redaxo::getAbsoluteUrl($confirmArticle, WV_Redaxo::CLANG_CURRENT, $params, '&');
			$extra  = array('#CONFIRMATION_URL#' => $link, '#CONFIRM_URL#' => $link);

			return self::sendToUser($user, 'mail_confirmation_subject', 'mail_confirmation_body', 'mail_confirmation_to', $extra);
		}

		return false;
	}

	public static function reportNewUserToAdmin(_WV16_User $user) {
		return self::sendToAdmin($user, 'mail_report_subject', 'mail_report_body');
	}

	public static function notifyUserOnActivation(_WV16_User $user) {
		return self::sendToUser($user, 'mail_activation_subject', 'mail_activation_body', 'mail_activation_to');
	}

	public static function sendPasswordRecovery(_WV16_User $user, $newPassword) {
		$extra = array('#PASSWORD#' => $newPassword, '#PASSWORT#' => $newPassword);
		return self::sendToUser($user, 'mail_recovery_subject', 'mail_recovery_body', 'mail_recovery_to', $extra);
	}

	/**
	 * @param _WV16_User $user  der Benutzer, für den die E-Mail bestimmt ist
	 * @param mixed      $code  der Bestätigungscode (false = keine Änderung, null = neu generieren, string = Code)
	 */
	public static function sendPasswordRecoveryRequest(_WV16_User $user, $code = false) {
		if ($code !== false) $user->setConfirmationCode($code);
		return self::sendToUser($user, 'mail_recoveryrequest_subject', 'mail_recoveryrequest_body', 'mail_recoveryrequest_to');
	}

	protected static function sendToUser(_WV16_User $user, $subject, $body, $to, $extra = array()) {
		$name    = self::replaceValues('mail_from_name', 'Administrator', $user, $extra);
		$email   = self::replaceValues('mail_from_email', 'admin@'.$_SERVER['SERVER_NAME'], $user, $extra);
		$subject = self::replaceValues($subject, 'Hallo #LOGIN#!', $user, $extra);
		$body    = self::replaceValues($body, '', $user, $extra);
		$to      = self::replaceValues($to, '#EMAIL#', $user, $extra);

		return self::send($email, $name, $to, '', $subject, $body);
	}

	protected static function sendToAdmin(_WV16_User $user, $subject, $body) {
		$name    = self::replaceValues('mail_from_name', 'Administrator', $user, $extra);
		$email   = self::replaceValues('mail_from_email', 'admin@'.$_SERVER['SERVER_NAME'], $user, $extra);
		$subject = self::replaceValues($subject, 'Hallo #LOGIN#!', $user, $extra);
		$body    = self::replaceValues($body, '', $user, $extra);

		return self::send($email, $name, $email, $name, $subject, $body);
	}

	protected static function replaceValues($setting, $default, _WV16_User $user, $extra = array()) {
		$text = WV16_Users::getConfig($setting, $default);
		$text = str_ireplace(array_keys($extra), array_values($extra), $text);

		return WV16_Users::replaceAttributes($text, $user);
	}

	protected static function send($from, $fromName, $to, $toName, $subject, $body) {
		try {
			WV_Mail::sendMail($from, $fromName, $to, $toName, $subject, $body, 'text/plain', 'UTf-8', true);
			return true;
		}
		catch (Exception $e) {
			return false;
		}
	}
}
