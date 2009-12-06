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

$id   = wv_request('id', 'int');
$func = wv_request('func', 'string');
$loop = 1;

while ($loop) { --$loop; switch ($func) {
#===============================================================================
# Benutzer hinzufügen
#===============================================================================
case 'add':

	$user = null;
	include _WV16_PATH.'templates/users/backend.phtml';
	break;

#===============================================================================
# Benutzer speichern
#===============================================================================
case 'do_add':

	$user      = null;
	$login     = wv_post('login', 'string');
	$password1 = wv_post('password', 'string');
	$password2 = wv_post('password2', 'string');
	$userType  = wv_post('type', 'int');
	$groups    = wv_postArray('groups', 'int');
	
	///////////////////////////////////////////////////////////////
	// Passwort und Benutzertyp checken
	
	try {
		if ($password1 != $password2) {
			throw new Exception('Die beiden Passwörter sind nicht identisch.');
		}
		
		$userTypeObj = _WV16_UserType::getInstance($userType);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}
	
	///////////////////////////////////////////////////////////////
	// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen
	
	$valuesToStore = _WV16::serializeUserForm($userType);
	
	if ($valuesToStore === null) {
		$errors = _WV16::getErrors();
		
		foreach ($errors as $idx => $e) {
			$errors[$idx] = $e['error'];
		}
		
		$errormsg = implode('<br />', $errors);
		$func     = 'add';
		++$loop;
		continue;
	}
	
	///////////////////////////////////////////////////////////////
	// Attribute sind OK. Ab in die Datenbank damit.

	try {
		$user = _WV16_User::register($login, $password1, $userType);
		
		// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.
		
		foreach ($valuesToStore as $value) {
			$user->setAttribute($value['attribute'], $value['value']);
		}
		
		// Standardmäßig ist der Benutzer nun in der Gruppe "noch nicht bestätigt".
		// Wir sind aber im Backend und ändern das daher gleich.
		
		$user->removeAllGroups();
		
		foreach ($groups as $group) {
			$user->addGroup($group);
		}
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}

	WV_Redaxo::success('Der Benutzer wurde erfolgreich angelegt.');

	$func = '';
	++$loop;
	continue;

#===============================================================================
# Benutzer löschen
#===============================================================================
case 'delete':
	
	try {
		$user = _WV16_User::getInstance($id);
		$user->delete();
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	$func = '';
	++$loop;
	continue;

#===============================================================================
# Benutzer bearbeiten
#===============================================================================
case 'edit':

	$user = _WV16_User::getInstance($id);
	include _WV16_PATH.'templates/users/backend.phtml';
	break;

#===============================================================================
# Metainformation speichern
#===============================================================================
case 'do_edit':

	if (isset($_POST['delete'])) {
		$func = 'delete';
		++$loop;
		continue;
	}

	$user      = null;
	$login     = trim(stripslashes(rex_post('login', 'string')));
	$password1 = trim(stripslashes(rex_post('password', 'string')));
	$password2 = trim(stripslashes(rex_post('password2', 'string')));
	$userType  = rex_post('type', 'int');
	$groups    = array();

	if ( isset($_POST['groups']) && is_array($_POST['groups']) ) {
		$groups = array_map('intval', $_POST['groups']);
	}
	
	///////////////////////////////////////////////////////////////
	// Passwort und Benutzertyp checken
	
	try {
		if ($password1 && $password1 != $password2) throw new Exception('Die beiden Passwörter sind nicht identisch.');
		$userTypeObj = _WV16_UserType::getInstance($userType);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}
	
	///////////////////////////////////////////////////////////////
	// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen
	
	$valuesToStore = _WV16::serializeUserForm($userType);
	
	if ($valuesToStore === null) {
		$errors = _WV16::getErrors();
		foreach ($errors as $idx => $e) $errors[$idx] = $e['error'];
		$errormsg = implode('<br />', $errors);
		$func     = 'edit';
		++$loop;
		continue;
	}
	
	///////////////////////////////////////////////////////////////
	// Attribute sind OK. Ab in die Datenbank damit.

	try {
		$user = _WV16_User::getInstance($id);
		$user->setUserType($userType); // löscht automatisch alle überhängenden Attribute
		$user->setLogin($login);
		if ($password1) $user->setPassword($password1);
		$user->update();
		
		foreach ($valuesToStore as $value) {
			$user->setAttribute($value['attribute'], $value['value']);
		}
		
		// Zu prüfen, in welcher Gruppe wir schon sind und in welcher nicht wäre
		// aufwendiger als die Gruppen alle neu einzufügen.
		$user->removeAllGroups();
		foreach ($groups as $group) $user->addGroup($group);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	WV2::success('Der Benutzer wurde erfolgreich bearbeitet.'.(_WV16_User::$sentActivationMail ? 
					' Der Nutzer wurde per Mail über seine Aktivierung benachrichtigt.' : ''));

	if (isset($_POST['apply'])) {
		$func = 'edit';
		++$loop;
		continue;
	}

	// kein break;

#===============================================================================
# Registrierte Benutzer auflisten
#===============================================================================
default:

	$offset  = abs(wv_get('offset', 'int', 0));
	$perPage = WV_Registry::get('wv16_users_per_page');
	$perPage = $perPage === null ? 20 : $perPage;
	$users   = WV16_Users::getAllUsers('login', 'asc', $offset, $perPage);
	
	require _WV16_PATH.'templates/users/table.phtml';
} }
