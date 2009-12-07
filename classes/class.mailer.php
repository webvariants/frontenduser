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
	
	public static function sendConfirmationRequest($user, $email, $name)
	{
		$clang = WV_Redaxo::clang();

		// Bestätigungscode erzeugen und speichern
		
		$code = WV16_Users::generateConfirmationCode($email);
		$user->setConfirmationCode($code);
		$user->update();
		
		// Body erzeugen
		
		$link = OOArticle::getArticleById(WV16_Users::getConfig('mail_validation_article'))->getUrl(array('confirm' => $code));
		$body = WV16_Users::getConfig('confirmation_body_'.$clang);
		$body = str_replace(
			array('#CONFIRMATION_URL#', '#CONFIRM_URL#'),
			substr(Utils::getAbsoluteURLBase(true), 0, -1).$link,
			$body
		);
		$body = self::replaceAttributes($body, $user);
		
		// Mail verschicken
		
		$defaultFrom = 'admin@'.$_SERVER['SERVER_NAME'];
		$mailer      = new PHPMailer();
		
		$mailer->AddAddress($email, $name);
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', $defaultFrom), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8';
		$mailer->Body    = $body;
		$mailer->Subject = WV16_Users::getConfig('confirmation_subject_'.$clang);
		
		return $mailer->Send();
	}

	public static function reportNewUserToAdmin($user)
	{
		$body = "Hallo,\n\nder Nutzer #LOGIN# hat sich soeben\nauf der Website ".Utils::getAbsoluteURLBase(true)." angemeldet.\n\n";
		$body = self::replaceAttributes($body, $user);
		
		$defaultFrom = 'admin@'.$_SERVER['SERVER_NAME'];
		$mailer      = new PHPMailer();
		
		$mailer->AddAddress(WV16_Users::getConfig('admin_mail'), WV16_Users::getConfig('admin_name'));
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', $defaultFrom), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8'; 
		$mailer->Body    = $body;
		$mailer->Subject = 'Neuer Nutzer angemeldet.';
		
		return $mailer->Send();
	}
	
	public static function notifyUserOnActivation(_WV16_User $user)
	{
		$clang = WV_Redaxo::clang();
		
		$toMail  = WV16_Users::getConfig('activation_to_'.$clang);
		$body    = WV16_Users::getConfig('activation_body_'.$clang);
		$subject = WV16_Users::getConfig('activation_subject_'.$clang);
		
		$to      = self::replaceAttributes($to, $user);
		$body    = self::replaceAttributes($body, $user);
		$subject = self::replaceAttributes($subject, $user);
		
		$mailer = new PHPMailer();
		
		$mailer->AddAddress($mail, $name);
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', 'admin@domain'), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8'; 
		$mailer->Body    = $body;
		$mailer->Subject = $subject;
		
		return $mailer->Send();
	}
	
	public static function sendPasswordRecovery(_WV16_User $user, $email, $password){
		global $REX;
				
		$body = WV16_Users::getConfig('password_recovery_body_'.$REX['CUR_CLANG']);
		
		$body = str_replace(array('#PASSWORD#', '#PASSWORT#'), $password, $body);
		$body = self::replaceAttributes($body, $user);
						
		$mailer = new PHPMailer();
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', 'admin@domain'), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8';
			
		$mailer->Body    = $body;
		$mailer->Subject = WV16_Users::getConfig('password_recovery_subject_'.$REX['CUR_CLANG']);
		$mailer->AddAddress($email);
			
		return $mailer->Send();
	}
	
	public static function replaceAttributes($body, $user)
	{
		$body = str_replace('#LOGIN#', $user->getLogin(), $body);
		
		foreach ($user->getAttributes() as $attr) {
			$code  = $attr->getAttributeName();
			$code  = preg_replace('#[^a-z0-9_]#i', '_', $code);
			$code  = strtoupper($code);
			$value = $attr->getValue();
			$value = is_array($value) ? implode(', ', $value) : $value;
			$body  = str_replace('#'.$code.'#', $value, $body);
		}
		
		return $body;
	}
}
