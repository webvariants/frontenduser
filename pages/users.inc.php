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
	$confirmed = wv_post('confirmed', 'boolean', false);
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

	$valuesToStore = _WV16_FrontendUser::serializeUserForm($userType);

	if ($valuesToStore === null) {
		$errors = _WV16_FrontendUser::getErrors();

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
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);

		$sql->beginTransaction();

		$user = _WV16_User::register($login, $password1, $userType, false);

		// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

		foreach ($valuesToStore as $value) {
			$user->setValue($value['attribute'], $value['value'], false);
		}

		// Standardmäßig ist der Benutzer nun in der Gruppe "noch nicht bestätigt".
		// Wir sind aber im Backend und ändern das daher gleich.

		$user->removeAllGroups(false);

		foreach ($groups as $group) {
			$user->addGroup($group, false);
		}

		$user->setConfirmed($confirmed, null, false);

		$sql->commit();
		$sql->setErrorMode($mode);
	}
	catch (Exception $e) {
		$sql->rollback();
		$errormsg = $e->getMessage();
		$func     = 'add';
		++$loop;
		continue;
	}

	rex_register_extension_point('WV16_USER_ADDED', $user, array('password' => $password1));
	WV_Redaxo::success('Der Benutzer wurde erfolgreich angelegt.');

	$func = '';
	++$loop;
	continue;

#===============================================================================
# Benutzer löschen
#===============================================================================
case 'delete':

	try {
		$user   = _WV16_User::getInstance($id);
		$values = $user->getValues(); // für den EP vor der Vernichtung retten
		$user->delete();
	}
	catch (Exception $e) {
		WV_Redaxo::error($e->getMessage());
	}

	rex_register_extension_point('WV16_USER_DELETED', $user, array('values' => $values));
	WV_Redaxo::success('Der Benutzer wurde erfolgreich gelöscht.');

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
# Benutzer speichern
#===============================================================================
case 'do_edit':

	if (isset($_POST['delete'])) {
		$func = 'delete';
		++$loop;
		continue;
	}

	$user      = null;
	$login     = wv_post('login', 'string');
	$password1 = wv_post('password', 'string');
	$password2 = wv_post('password2', 'string');
	$userType  = wv_post('type', 'int');
	$confirmed = wv_post('confirmed', 'boolean', false);
	$groups    = wv_postArray('groups', 'int');

	///////////////////////////////////////////////////////////////
	// Passwort und Benutzertyp checken

	try {
		// Wir initialisieren das Objekt jetzt schon, damit wir im catch-Block
		// direkt ein edit-Formular anbieten können.

		$user = _WV16_User::getInstance($id);

		if ($password1 && $password1 != $password2) {
			throw new WV_InputException('Die beiden Passwörter sind nicht identisch.');
		}

		$userTypeObj = _WV16_UserType::getInstance($userType);
	}
	catch (Exception $e) {
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	///////////////////////////////////////////////////////////////
	// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

	$valuesToStore = _WV16_FrontendUser::serializeUserForm($userType);

	if ($valuesToStore === null) {
		$errors = _WV16_FrontendUser::getErrors();

		foreach ($errors as $idx => $e) {
			$errors[$idx] = $e['error'];
		}

		$errormsg = implode('<br />', $errors);
		$func     = 'edit';
		++$loop;
		continue;
	}

	$wasActivated = $user->wasEverActivated();

	///////////////////////////////////////////////////////////////
	// Attribute sind OK. Ab in die Datenbank damit.

	try {
		$sql  = WV_SQLEx::getInstance();
		$mode = $sql->setErrorMode(WV_SQLEx::THROW_EXCEPTION);

		$sql->beginTransaction();

		$user->setUserType($userType); // löscht automatisch alle überhängenden Attribute
		$user->setLogin($login);

		if (!empty($password1)) {
			$user->setPassword($password1);
		}

		$user->update(false);

		foreach ($valuesToStore as $value) {
			$user->setValue($value['attribute'], $value['value'], false);
		}

		// Zu prüfen, in welcher Gruppe wir schon sind und in welcher nicht wäre
		// aufwendiger als die Gruppen alle neu einzufügen.

		$user->removeAllGroups(false);

		foreach ($groups as $group) {
			$user->addGroup($group, false);
		}

		$user->setConfirmed($confirmed, null, false);

		$sql->commit();
		$sql->setErrorMode($mode);
	}
	catch (Exception $e) {
		$sql->rollback();
		$errormsg = $e->getMessage();
		$func     = 'edit';
		++$loop;
		continue;
	}

	$params = !empty($password1) ? array('password' => $password1) : array();
	rex_register_extension_point('WV16_USER_UPDATED', $user, $params);
	WV_Redaxo::success('Der Benutzer wurde erfolgreich bearbeitet.');

	// Bei der ersten Aktivierung benachrichtigen wir den Benutzer.

	$firstTimeActivation = !$wasActivated && $user->wasEverActivated();

	if ($firstTimeActivation) {
		try {
			WV16_Mailer::notifyUserOnActivation($user);
			WV_Redaxo::success('Der Nutzer wurde per Mail über seine Aktivierung benachrichtigt.');
		}
		catch (Exception $e) {
			WV_Redaxo::error('Das Senden der Aktivierungsbenachrichtigung schlug fehl: '.wv_html($e->getMessage()).'.');
		}
	}

	// kein break;
}}
