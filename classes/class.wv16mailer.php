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
	
	public static function sendConfirmationRequest($user, $email, $name) {
		global $REX;

		// save code to registry
		
		$code = WV16_Users::generateConfirmationCode($email);
		$code_db = WV16_Users::getConfig('code_db', array());
		$code_db[$email] = $code;
		WV16_Users::setConfig('code_db', $code_db);
		
		// Body erzeugen
		
		$link = OOArticle::getArticleById(WV16_Users::getConfig('mail_validation_article'))->getUrl(array('confirm' => $code));
		$body = WV16_Users::getConfig('confirmation_body_'.$REX['CUR_CLANG']);
		$body = str_replace(
			array('#CONFIRMATION_URL#', '#CONFIRM_URL#'),
			substr(Utils::getAbsoluteURLBase(true), 0, -1).$link,
			$body
		);
		$body = self::replaceAttributes($body, $user);
		
		// Mail verschicken
		
		$mailer = new PHPMailer();
		
		$mailer->AddAddress($email, $name);
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', 'admin@domain'), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8';
		$mailer->Body    = $body;
		$mailer->Subject = WV16_Users::getConfig('confirmation_subject_'.$REX['CUR_CLANG']);
		
		return $mailer->Send();
	}

	public static function reportNewUserToAdmin($user) {
		$body = "Hallo,\n\nder Nutzer #LOGIN# hat sich soeben\nauf der Website ".Utils::getAbsoluteURLBase(true)." angemeldet.\n\n";
		$body = self::replaceAttributes($body, $user);
		
		$mailer = new PHPMailer();
		
		$mailer->AddAddress(WV16_Users::getConfig('admin_mail'), WV16_Users::getConfig('admin_name'));
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', 'admin@domain'), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8'; 
		$mailer->Body    = $body;
		$mailer->Subject = 'Neuer Nutzer angemeldet.';
		
		return $mailer->Send();
	}
	
	public static function notifyUserOnActivation(_WV16_User $user) {
		global $REX;
		
		// UNSCHÖN: hier steckt vorwissen aus dem aci-projekt drin!
		// 		normalerweise kann diese methode überhaupt nicht wissen, dass es 
		// 		die nutzerattribute name, fname und mail gibt. andererseits
		// 		sollten wir überlegen, ob wir nicht einige essentiellen attribute
		// 		einführen wollen, ohne die ein großteil dieser anwendung ohnehin
		// 		nicht funktionieren würde (zb email-adresse)
		
		$name = $user->getAttribute('fname')->getValue().' '.$user->getAttribute('name')->getValue();
		$mail = $user->getAttribute('mail')->getValue();
		$body = WV16_Users::getConfig('activation_body_'.$REX['CUR_CLANG']);
		$body = self::replaceAttributes($body, $user);
		
		$mailer = new PHPMailer();
		
		$mailer->AddAddress($mail, $name);
		$mailer->SetFrom(WV16_Users::getConfig('admin_mail', 'admin@domain'), WV16_Users::getConfig('admin_name', 'admin'));
		$mailer->CharSet = 'utf-8'; 
		$mailer->Body    = $body;
		$mailer->Subject = WV16_Users::getConfig('activation_subject_'.$REX['CUR_CLANG']);
		
		return $mailer->Send();
	}
	
	public static function replaceAttributes($body, $user) {
		$body = str_replace('#LOGIN#', $user->getLogin(), $body);
		
		foreach ($user->getAttributes() as $attr) {
			$code = $attr->getAttributeName();
			$code = preg_replace('#[^a-z0-9_]#i', '_', $code);
			$code = strtoupper($code);
			$body = str_replace('#'.$code.'#', $attr->getValue(), $body);
		}
		
		return $body;
	}
}
