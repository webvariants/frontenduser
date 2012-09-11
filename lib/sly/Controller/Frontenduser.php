<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser extends sly_Controller_Backend implements sly_Controller_Interface {
	private $errors = array();
	private $init   = false;

	protected function getViewFolder() {
		return _WV16_PATH.'templates/';
	}

	protected function init() {
		if ($this->init) return;
		$this->init = true;

		$layout = sly_Core::getLayout();
		$layout->addCSSFile('../data/dyn/public/webvariants/frontenduser/css/wv16.less');
		$layout->addJavaScriptFile('../data/dyn/public/webvariants/frontenduser/js/frontenduser.min.js');
		$layout->pageHeader(t('frontenduser_title'));
	}

	public function indexAction() {
		$this->init();

		$search  = sly_Table::getSearchParameters('users');
		$paging  = sly_Table::getPagingParameters('users', true, false);
		$sorting = sly_Table::getSortingParameters('login', array('login', 'registered'));
		$where   = 'deleted = 0';
		$sql     = WV_SQL::getInstance();

		if (!empty($search)) {
			$where .= ' AND (`login` LIKE ? OR `registered` LIKE ?)';
			$where  = str_replace('?', $sql->quote('%'.$search.'%'), $where);
		}

		$users = WV16_Provider::getUsers($where, $sorting['sortby'], $sorting['direction'], $paging['start'], $paging['elements']);
		$total = WV16_Provider::getTotalUsers($where);

		print $this->render('users/table.phtml', compact('users', 'total'));
	}

	public function addAction() {
		$this->init();

		$user = null;
		$func = 'add';

		print $this->render('users/backend.phtml', compact('user', 'func'));
	}

	public function do_addAction() {
		$this->init();

		$user      = null;
		$login     = sly_post('login', 'string');
		$password1 = sly_post('password', 'string');
		$password2 = sly_post('password2', 'string');
		$userType  = sly_post('type', 'string');
		$activated = sly_post('activated', 'boolean', false);
		$confirmed = sly_post('confirmed', 'boolean', false);
		$groups    = sly_postArray('groups', 'string');

		///////////////////////////////////////////////////////////////
		// Passwort und Benutzertyp checken

		try {
			if ($password1 != $password2) {
				throw new Exception('Die beiden Passwörter sind nicht identisch.');
			}

			// Holzhammer-Methode
			WV16_Factory::getUserType($userType);
		}
		catch (Exception $e) {
			print sly_Helper_Message::warn($e->getMessage());
			return $this->addAction();
		}

		///////////////////////////////////////////////////////////////
		// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

		$valuesToStore = $this->serializeForm($userType);

		if ($valuesToStore === null) {
			$errors = $this->errors;

			foreach ($errors as $idx => $e) {
				$errors[$idx] = sly_translate(WV16_Factory::getAttribute($e['attribute'])->getTitle()).': '.$e['error'];
			}

			$errormsg = implode('<br />', $errors);
			print sly_Helper_Message::warn($errormsg);
			return $this->addAction();
		}

		///////////////////////////////////////////////////////////////
		// Attribute sind OK. Ab in die Datenbank damit.

		try {
			$user = _WV16_User::register($login, $password1, $userType);
			$user->setConfirmed($confirmed);
			$user->setActivated($activated);

			// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

			foreach ($valuesToStore as $name => $value) {
				$user->setValue($name, $value);
			}

			// Gruppen hinzufügen

			foreach ($groups as $group) {
				$user->addGroup($group);
			}

			$user->update();
		}
		catch (Exception $e) {
			print sly_Helper_Message::warn($e->getMessage());
			return $this->addAction();
		}

		sly_Core::dispatcher()->notify('WV16_USER_ADDED', $user, array('password' => $password1));
		print sly_Helper_Message::info('Der Benutzer wurde erfolgreich angelegt.');

		$this->indexAction();
	}

	public function editAction() {
		$this->init();

		$id   = sly_request('id', 'int');
		$user = _WV16_User::getInstance($id);
		$func = 'edit';

		print $this->render('users/backend.phtml', compact('user', 'func'));
	}

	public function do_editAction() {
		$this->init();

		if (isset($_POST['delete'])) {
			return $this->deleteAction();
		}

		$id        = sly_request('id', 'int');
		$user      = null;
		$login     = sly_post('login', 'string');
		$password1 = sly_post('password', 'string');
		$password2 = sly_post('password2', 'string');
		$userType  = sly_post('type', 'string');
		$activated = sly_post('activated', 'boolean', false);
		$confirmed = sly_post('confirmed', 'boolean', false);
		$groups    = sly_postArray('groups', 'string');

		///////////////////////////////////////////////////////////////
		// Passwort und Benutzertyp checken

		try {
			// Wir initialisieren das Objekt jetzt schon, damit wir im catch-Block
			// direkt ein edit-Formular anbieten können.

			$user = WV16_Factory::getUserByID($id);

			if ($password1 && $password1 != $password2) {
				throw new Exception('Die beiden Passwörter sind nicht identisch.');
			}

			WV16_Factory::getUserType($userType);
		}
		catch (Exception $e) {
			print sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		///////////////////////////////////////////////////////////////
		// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

		$valuesToStore = $this->serializeForm($userType);

		if ($valuesToStore === null) {
			$errors = $this->errors;

			foreach ($errors as $idx => $e) {
				$errors[$idx] = sly_translate(WV16_Factory::getAttribute($e['attribute'])->getTitle()).': '.$e['error'];
			}

			print sly_Helper_Message::warn(implode('<br />', $errors));
			return $this->editAction();
		}

		$wasActivated = $user->wasEverActivated();

		///////////////////////////////////////////////////////////////
		// Attribute sind OK. Ab in die Datenbank damit.

		try {
			WV_SQL::getInstance()->beginTransaction();

			$user->setUserType($userType);
			$user->setLogin($login);

			if (!empty($password1)) {
				$user->setPassword($password1);
			}

			// Attributmenge aktualisieren
			$user->update();

			foreach ($valuesToStore as $name => $value) {
				$user->setValue($name, $value);
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

			WV_SQL::getInstance()->commit();
		}
		catch (Exception $e) {
			WV_SQL::getInstance()->rollBack();
			print sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		$params = !empty($password1) ? array('password' => $password1) : array();
		sly_Core::dispatcher()->notify('WV16_USER_UPDATED', $user, $params);
		print sly_Helper_Message::info('Der Benutzer wurde erfolgreich bearbeitet.');

		// Bei der ersten Aktivierung benachrichtigen wir den Benutzer.

		$firstTimeActivation = !$wasActivated && $user->wasEverActivated();

		if ($firstTimeActivation) {
			try {
				WV_UserWorkflows::notifyUserOnActivation($user);
				print sly_Helper_Message::info('Der Nutzer wurde per Mail über seine Aktivierung benachrichtigt.');
			}
			catch (Exception $e) {
				print sly_Helper_Message::warn('Das Senden der Aktivierungsbenachrichtigung schlug fehl: '.sly_html($e->getMessage()).'.');
			}
		}

		$this->indexAction();
	}

	public function deleteAction() {
		$this->init();

		try {
			$id     = sly_request('id', 'int');
			$user   = _WV16_User::getInstance($id);
			$values = $user->getValues(); // für den EP vor der Vernichtung retten

			$user->delete();
		}
		catch (Exception $e) {
			print sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		sly_Core::dispatcher()->notify('WV16_USER_DELETED', $user, array('values' => $values));
		print sly_Helper_Message::info('Der Benutzer wurde erfolgreich gelöscht.');

		$this->indexAction();
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('frontenduser', 'users'));
	}

	private function serializeForm($userType) {
		$requiredAttrs  = WV16_Provider::getAttributes($userType);
		$availableAttrs = WV16_Provider::getAttributes();
		$values         = array();

		foreach ($availableAttrs as $name => $attr) {
			// Wir lassen keine Daten zu, die nicht zu diesem Benutzertyp gehören.
			if (!isset($requiredAttrs[$name])) continue;

			try {
				$values[$name] = $attr->serializeForm();
			}
			catch (Exception $e) {
				$this->errors[] = array(
					'attribute' => $name,
					'error'     => $e->getMessage()
				);
			}
		}

		return empty($this->errors) ? $values : null;
	}

	protected function getAttributesToDisplay($assigned, $required) {
		$available = WV16_Provider::getAttributes();
		$required  = array_keys($required);
		$return    = array();

		foreach ($available as $name => $attribute) {
			$value         = isset($assigned[$name]) ? $assigned[$name] : null;
			$req           = in_array($name, $required);
			$return[$name] = array('attribute' => $attribute, 'value' => $value, 'required' => $req);
		}

		return $return;
	}
}
