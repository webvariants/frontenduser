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

abstract class WV16_Mailer
{
	public static function sendConfirmationRequest(_WV16_User $user, $parameterName = 'confirm')
	{
		// Bestätigungscode erzeugen und speichern
		
		$code = WV16_Users::generateConfirmationCode($email);
		$user->setConfirmationCode($code);
		$user->update();
		
		// Artikel finden
		
		$confirmArticle = OOArticle::getArticleById(WV16_Users::getConfig('validation_article'));
		
		if ($confirmArticle) {
			$link = $confirmArticle->getUrl(array($parameterName => $code));
			$link = WV_Redaxo::getBaseUrl(true).'/'.$link;
			
			// Mail verschicken
			
			$mailer = self::getMailer($user, 'mail_confirmation_subject', 'mail_confirmation_body');
			$mailer->AddAddress($email, $name);
			
			// Im Body ersetzen wir noch zusätzliche den Bestätigungslink
			
			$mailer->Body = str_replace(array('#CONFIRMATION_URL#', '#CONFIRM_URL#'), $link, $mailer->Body);
			
			return $mailer->Send();
		}
		
		return false;
	}

	public static function reportNewUserToAdmin(_WV16_User $user)
	{
		$name   = self::replaceValues(WV16_Users::getConfig('mail_from_name', 'Administrator'), $user);
		$email  = self::replaceValues(WV16_Users::getConfig('mail_from_email', 'admin@'.$_SERVER['SERVER_NAME']), $user);
		$mailer = self::getMailer($user, 'mail_report_subject', 'mail_report_body');
		
		$mailer->AddAddress($email, $name);
		return $mailer->Send();
	}
	
	public static function notifyUserOnActivation(_WV16_User $user)
	{
		$address = self::replaceValues(WV16_Users::getConfig('mail_activation_to', '#EMAIL#'), $user);
		$mailer  = self::getMailer($user, 'mail_activation_subject', 'mail_activation_body');
		
		self::addAddress($mailer, $address);
		return $mailer->Send();
	}
	
	public static function sendPasswordRecovery(_WV16_User $user, $newPassword)
	{
		$address = self::replaceValues(WV16_Users::getConfig('mail_recovery_to', '#EMAIL#'), $user);
		$mailer  = self::getMailer($user, 'mail_recovery_subject', 'mail_recovery_body');
		
		// Im Body ersetzen wir noch zusätzliche den Platzhalter für das Passwort
		
		$mailer->Body = str_replace(array('#PASSWORD#', '#PASSWORT#'), $newPassword, $mailer->Body);
		
		self::addAddress($mailer, $address);
		return $mailer->Send();
	}
	
	public static function replaceValues($body, _WV16_User $user)
	{
		$body = str_replace('#LOGIN#', $user->getLogin(), $body);
		
		foreach ($user->getValues() as $attr) {
			$code  = $attr->getAttributeName();
			$code  = preg_replace('#[^a-z0-9_]#i', '_', $code);
			$code  = strtoupper($code);
			$value = $attr->getValue();
			$value = is_array($value) ? implode(', ', $value) : $value;
			$body  = str_replace('#'.$code.'#', $value, $body);
		}
		
		return $body;
	}
	
	protected static function getMailer(_WV16_User $user, $subjectSetting = null, $bodySetting = null)
	{
		$name   = self::replaceValues(WV16_Users::getConfig('mail_from_name', 'Administrator'), $user);
		$email  = self::replaceValues(WV16_Users::getConfig('mail_from_email', 'admin@'.$_SERVER['SERVER_NAME']), $user);
		$mailer = new PHPMailerLite(true);
		
		$mailer->SetFrom($email, $name);
		$mailer->CharSet = 'utf-8';
		
		// Betreff setzen, falls möglich
		
		if ($subjectSetting !== null) {
			$subject = WV16_Users::getConfig($subjectSetting);
			$subject = self::replaceValues($subject, $user);
			
			$mailer->Subject = $subject;
		}
		
		// Inhalt setzen, falls möglich
		
		if ($bodySetting !== null) {
			$body = WV16_Users::getConfig($bodySetting);
			$body = self::replaceValues($body, $user);
			
			$mailer->Body = $body;
		}
		
		return $mailer;
	}
	
	protected static function addAddress(PHPMailerLite $mailer, $address)
	{
		$address = trim($address);
		
		if (preg_match('#(.+?)\s*<\s*(.+?)\s*>#', $address, $match)) {
			$mailer->AddAddress(trim($match[2]), trim($match[1]));
		}
		else {
			$mailer->AddAddress($address);
		}
	}
}
