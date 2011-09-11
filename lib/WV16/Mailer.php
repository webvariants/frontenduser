<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class WV16_Mailer {
	protected function __construct() {
		/* empty */
	}

	/* public API */

	public static function sendConfirmationRequest(_WV16_User $user, $parameterName = 'confirm') {
		$instance = new self();
		$instance->sendConfirmationRequestBase($user, $parameterName);
	}

	public static function reportNewUserToAdmin(_WV16_User $user) {
		$instance = new self();
		return $instance->sendToAdmin($user, 'report');
	}

	public static function notifyUserOnActivation(_WV16_User $user) {
		$instance = new self();
		return $instance->sendToUser($user, 'activation');
	}

	public static function sendPasswordRecovery(_WV16_User $user, $newPassword) {
		$extra    = array('#PASSWORD#' => $newPassword, '#PASSWORT#' => $newPassword);
		$instance = new self();

		return $instance->sendToUser($user, 'recovery', $extra);
	}

	/**
	 * @param _WV16_User $user  der Benutzer, für den die E-Mail bestimmt ist
	 * @param mixed      $code  der Bestätigungscode (false = keine Änderung, null = neu generieren, string = Code)
	 */
	public static function sendPasswordRecoveryRequest(_WV16_User $user, $code = false) {
		$instance = new self();
		$instance->sendPasswordRecoveryRequestBase($user, $code);
	}

	/* inheritable methods */

	protected function sendPasswordRecoveryRequestBase(_WV16_User $user, $code) {
		if ($code !== false) {
			$user->setConfirmationCode($code);
			$user->update();
		}

		$link = sly_Util_Article::findById(WV16_Users::getConfig('articles', 'recovery'));

		if ($link) {
			$params = array('code' => $user->getConfirmationCode());
			$link   = WV_Sally::getAbsoluteUrl($link, WV_Sally::CLANG_CURRENT, $params, '&');
			$extra  = array('#LINK#' => $link);

			return $this->sendToUser($user, 'recoveryrequest', $extra);
		}

		return false;
	}

	protected function sendConfirmationRequestBase(_WV16_User $user, $parameterName) {
		// Code holen

		$code = $user->getConfirmationCode();

		if (empty($code)) {
			// Bestätigungscode erzeugen und speichern

			$code = WV16_Users::generateConfirmationCode($user->getLogin());
			$user->setConfirmationCode($code);
			$user->update();
		}

		// Artikel finden

		$confirmArticle = sly_Util_Article::findById(WV16_Users::getConfig('articles', 'validation'));

		if ($confirmArticle) {
			$params = array($parameterName => $code);
			$link   = WV_Sally::getAbsoluteUrl($confirmArticle, WV_Sally::CLANG_CURRENT, $params, '&');
			$extra  = array('#CONFIRMATION_URL#' => $link, '#CONFIRM_URL#' => $link);

			return $this->sendToUser($user, 'confirmation', $extra);
		}

		return false;
	}

	protected function sendToUser(_WV16_User $user, $ns, $extra = array()) {
		$name    = $this->replaceValues('mail',      'from_name',  'Administrator',                   $user, $extra);
		$email   = $this->replaceValues('mail',      'from_email', 'admin@'.sly_Util_HTTP::getHost(), $user, $extra);
		$subject = $this->replaceValues('mail.'.$ns, 'subject',    'Hallo #LOGIN#!',                  $user, $extra);
		$body    = $this->replaceValues('mail.'.$ns, 'body',       '',                                $user, $extra);
		$to      = $this->replaceValues('mail.'.$ns, 'to',         '#EMAIL#',                         $user, $extra);

		return $this->send($email, $name, $to, '', $subject, $body);
	}

	protected function sendToAdmin(_WV16_User $user, $ns, $extra = array()) {
		$name    = $this->replaceValues('mail',      'from_name',  'Administrator',                   $user, $extra);
		$email   = $this->replaceValues('mail',      'from_email', 'admin@'.sly_Util_HTTP::getHost(), $user, $extra);
		$subject = $this->replaceValues('mail.'.$ns, 'subject',    'Hallo #LOGIN#!',                  $user, $extra);
		$body    = $this->replaceValues('mail.'.$ns, 'body',       '',                                $user, $extra);

		return $this->send($email, $name, $email, $name, $subject, $body);
	}

	protected function replaceValues($namespace, $setting, $default, _WV16_User $user, $extra = array()) {
		$text = WV16_Users::getConfig($namespace, $setting, $default);
		$text = str_ireplace(array_keys($extra), array_values($extra), $text);

		return WV16_Users::replaceAttributes($text, $user);
	}

	protected function send($from, $fromName, $to, $toName, $subject, $body) {
		try {
			$mail = sly_Mail::factory();
			$mail->addTo($to, $toName);
			$mail->setFrom($from, $fromName);
			$mail->setSubject($subject);
			$mail->setBody($body);

			return $mail->send();
		}
		catch (Exception $e) {
			return false;
		}
	}
}
