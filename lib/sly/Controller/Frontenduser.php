<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser extends sly_Controller_Sally {
	private $errors = array();

	protected function init() {
		$pages = array(
			''           => 'Benutzer',
//			'groups'     => 'Gruppen',
			'types'      => 'Benutzertypen',
			'attributes' => 'Attribute'
		);

		if (file_exists(SLY_DEVELOPFOLDER.'/frontenduser-exports.yml')) {
			$pages['exports'] = 'Export';
		}

		foreach ($pages as $key => $value) {
			if (WV_Sally::isAdminOrHasPerm('frontenduser['.$key.']')) {
				$subpages[] = array($key, $value);
			}
		}

		sly_Core::getNavigation()->get('frontenduser', 'addon')->addSubpages($subpages);

		$layout = sly_Core::getLayout();
		$layout->addCSSFile('../data/dyn/public/frontenduser/css/wv16.css');
		$layout->addJavaScriptFile('../data/dyn/public/frontenduser/js/frontenduser.min.js');
		$layout->pageHeader(t('frontenduser_title'), $subpages);
	}

	protected function index() {
		$search  = sly_Table::getSearchParameters('users');
		$paging  = sly_Table::getPagingParameters('users', true, false);
		$sorting = sly_Table::getSortingParameters('login', array('login', 'registered'));
		$where   = '1';

		if (!empty($search)) {
			$searchSQL = ' AND (`login` = ? OR `registered` = ?)';
			$searchSQL = str_replace('=', 'LIKE', $searchSQL);
			$searchSQL = str_replace('?', '"%'.mysql_real_escape_string($search).'%"', $searchSQL);

			$where .= $searchSQL;
		}

		$users = WV16_Users::getAllUsers($where, $sorting['sortby'], $sorting['direction'], $paging['start'], $paging['elements']);
		$total = WV16_Users::getTotalUsers($where);

		$this->render('addons/frontenduser/templates/users/table.phtml', compact('users', 'total'));
	}

	protected function add() {
		$user = null;
		$func = 'add';
		$this->render('addons/frontenduser/templates/users/backend.phtml', compact('user', 'func'));
	}

	protected function do_add() {
		$user      = null;
		$login     = sly_post('login', 'string');
		$password1 = sly_post('password', 'string');
		$password2 = sly_post('password2', 'string');
		$userType  = sly_post('type', 'int');
		$activated = sly_post('activated', 'boolean', false);
		$confirmed = sly_post('confirmed', 'boolean', false);
		$groups    = sly_postArray('groups', 'int');

		///////////////////////////////////////////////////////////////
		// Passwort und Benutzertyp checken

		try {
			if ($password1 != $password2) {
				throw new Exception('Die beiden Passwörter sind nicht identisch.');
			}

			$userTypeObj = _WV16_UserType::getInstance($userType);
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->add();
		}

		///////////////////////////////////////////////////////////////
		// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

		$valuesToStore = $this->serializeForm($userType);

		if ($valuesToStore === null) {
			$errors = $this->errors;

			foreach ($errors as $idx => $e) {
				$errors[$idx] = $e['error'];
			}

			$errormsg = implode('<br />', $errors);
			print rex_warning($errormsg);
			return $this->add();
		}

		///////////////////////////////////////////////////////////////
		// Attribute sind OK. Ab in die Datenbank damit.

		try {
			$user = _WV16_User::register($login, $password1, $userType);
			$user->setConfirmed($confirmed);
			$user->setActivated($activated);

			// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

			foreach ($valuesToStore as $value) {
				$user->setValue($value['attribute'], $value['value']);
			}

			// Standardmäßig ist der Benutzer nun in der Gruppe "noch nicht bestätigt".
			// Wir sind aber im Backend und ändern das daher gleich.

			$user->removeAllGroups();

			foreach ($groups as $group) {
				$user->addGroup($group);
			}

			$user->update();
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->add();
		}

		sly_Core::dispatcher()->notify('WV16_USER_ADDED', $user, array('password' => $password1));
		print rex_info('Der Benutzer wurde erfolgreich angelegt.');

		$this->index();
	}

	protected function edit() {
		$id   = sly_request('id', 'int');
		$user = _WV16_User::getInstance($id);
		$func = 'edit';
		$this->render('addons/frontenduser/templates/users/backend.phtml', compact('user', 'func'));
	}

	protected function do_edit() {
		if (isset($_POST['delete'])) {
			return $this->delete();
		}

		$id        = sly_request('id', 'int');
		$user      = null;
		$login     = sly_post('login', 'string');
		$password1 = sly_post('password', 'string');
		$password2 = sly_post('password2', 'string');
		$userType  = sly_post('type', 'int');
		$activated = sly_post('activated', 'boolean', false);
		$confirmed = sly_post('confirmed', 'boolean', false);
		$groups    = sly_postArray('groups', 'int');

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
			print rex_warning($e->getMessage());
			return $this->edit();
		}

		///////////////////////////////////////////////////////////////
		// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

		$valuesToStore = $this->serializeForm($userType);

		if ($valuesToStore === null) {
			$errors = $this->errors();

			foreach ($errors as $idx => $e) {
				$errors[$idx] = $e['error'];
			}

			print rex_warning(implode('<br />', $errors));
			return $this->edit();
		}

		$wasActivated = $user->wasEverActivated();

		///////////////////////////////////////////////////////////////
		// Attribute sind OK. Ab in die Datenbank damit.

		try {
			$user->setUserType($userType); // löscht automatisch alle überhängenden Attribute
			$user->setLogin($login);

			if (!empty($password1)) {
				$user->setPassword($password1);
			}

			foreach ($valuesToStore as $value) {
				$user->setValue($value['attribute'], $value['value']);
			}

			// Zu prüfen, in welcher Gruppe wir schon sind und in welcher nicht wäre
			// aufwendiger als die Gruppen alle neu einzufügen.

			$user->removeAllGroups();

			foreach ($groups as $group) {
				$user->addGroup($group);
			}

			$user->setConfirmed($confirmed, null);
			$user->setActivated($activated);
			$user->update();
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->edit();
		}

		$params = !empty($password1) ? array('password' => $password1) : array();
		sly_Core::dispatcher()->notify('WV16_USER_UPDATED', $user, $params);
		print rex_info('Der Benutzer wurde erfolgreich bearbeitet.');

		// Bei der ersten Aktivierung benachrichtigen wir den Benutzer.

		$firstTimeActivation = !$wasActivated && $user->wasEverActivated();

		if ($firstTimeActivation) {
			try {
				WV16_Mailer::notifyUserOnActivation($user);
				print rex_info('Der Nutzer wurde per Mail über seine Aktivierung benachrichtigt.');
			}
			catch (Exception $e) {
				print rex_warning('Das Senden der Aktivierungsbenachrichtigung schlug fehl: '.sly_html($e->getMessage()).'.');
			}
		}

		$this->index();
	}

	protected function delete() {
		try {
			$id     = sly_request('id', 'int');
			$user   = _WV16_User::getInstance($id);
			$values = $user->getValues(); // für den EP vor der Vernichtung retten
			$user->delete();
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_USER_DELETED', $user, array('values' => $values));
		print rex_info('Der Benutzer wurde erfolgreich gelöscht.');

		$this->index();
	}

	protected function checkPermission() {
		return WV_Sally::isAdminOrHasPerm('frontenduser[]');
	}

	private function serializeForm($userType) {
		$requiredAttrs  = WV16_Users::getAttributesForUserType($userType);
		$availableAttrs = WV16_Users::getAttributesForUserType(-1);
		$valuesToStore  = array();

		foreach ($availableAttrs as $attr) {
			$isRequired = false;

			foreach ($requiredAttrs as $rattr) {
				if ($rattr->getID() == $attr->getID()) {
					$isRequired = true;
					break;
				}
			}

			// Wir lassen keine Daten zu, die nicht zu diesem Benutzertyp gehören.

			if (!$isRequired) {
				continue;
			}

			try {
				$inputForUser = WV_Datatype::call($attr->getDatatypeID(), 'serializeForm', $attr);

				// Keine gültige Eingabe aber benötigtes Feld? -> Abbruch!

				if ($inputForUser === false && $isRequired) {
					$this->errors[] = array(
						'attribute' => $attr->getID(),
						'error'     => 'Diese Angabe ist ein Pflichtfeld!'
					);
					continue;
				}

				// Woohoo! Eine Eingabe! Die merken wir uns.

				$valuesToStore[] = array(
					'value'     => $inputForUser,
					'attribute' => $attr->getID()
				);
			}
			catch (WV_DatatypeException $e) {
				$this->errors[] = array(
					'attribute' => $attr->getID(),
					'error'     => $e->getMessage()
				);
			}
		}

		return empty($this->errors) ? $valuesToStore : null;
	}

	protected function getAttributesToDisplay($available, $assigned, $required) {
		$return = array();

		foreach ($available as $attribute) {
			$metadata = null;
			$req      = false;

			foreach ($assigned as $data) {
				if ($data->getAttributeID() == $attribute->getID()) {
					$metadata = $data;
					break;
				}
			}

			foreach ($required as $rinfo) {
				if ($rinfo->getID() == $attribute->getID()) {
					$req = true;
					break;
				}
			}

			$return[] = array('attribute' => $attribute, 'data' => $metadata, 'required' => $req);
		}

		return $return;
	}
}
