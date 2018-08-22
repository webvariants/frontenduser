<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace wv\FrontendUser\Controller;

use wv\FrontendUser\Factory;
use wv\FrontendUser\Provider;
use wv\FrontendUser\User;

class UserController extends BaseController {
	public function indexAction() {
		$this->init();

		$search  = \sly_Table::getSearchParameters('users');
		$paging  = \sly_Table::getPagingParameters('users', true, false);
		$sorting = \sly_Table::getSortingParameters('login', array('login', 'registered'));
		$where   = 'deleted = 0';
		$sql     = \WV_SQL::getInstance();

		if (!empty($search)) {
			$where .= ' AND (`login` LIKE ? OR `registered` LIKE ?)';
			$where_before_replace = $where;
			$where  = str_replace('?', $sql->quote('%'.$search.'%'), $where);
		}

		$where = \sly_Core::dispatcher()->filter('WV_FE_USER_CONTROLLER_WHERE', $where, array('search' => $search, 'where_before_replace' => $where_before_replace, 'sql' => $sql));

		$users = Provider::getUsers($where, $sorting['sortby'], $sorting['direction'], $paging['start'], $paging['elements']);
		$total = Provider::getTotalUsers($where);

		$this->render('users/table.phtml', compact('users', 'total'), false);
	}

	public function addAction() {
		$this->init();

		$user = null;
		$func = 'add';

		$this->render('users/backend.phtml', compact('user', 'func'), false);
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
				throw new \Exception('Die beiden Passwörter sind nicht identisch.');
			}

			// Holzhammer-Methode
			Factory::getUserType($userType);
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->addAction();
		}

		///////////////////////////////////////////////////////////////
		// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

		$valuesToStore = $this->serializeForm($userType);

		if ($valuesToStore === null) {
			$errors = $this->errors;

			foreach ($errors as $idx => $e) {
				$errors[$idx] = sly_translate(Factory::getAttribute($e['attribute'])->getTitle()).': '.$e['error'];
			}

			$errormsg = implode('<br />', $errors);
			print \sly_Helper_Message::warn($errormsg);
			return $this->addAction();
		}

		///////////////////////////////////////////////////////////////
		// Attribute sind OK. Ab in die Datenbank damit.

		try {
			$user = User::register($login, $password1, $userType);
			$user->setConfirmed($confirmed);
			$user->setActivated($activated);

			// Attribute können erst gesetzt werden, nachdem der Benutzer angelegt wurde.

			foreach ($valuesToStore as $name => $value) {
				$user->setSerializedValue($name, $value);
			}

			// Gruppen hinzufügen

			foreach ($groups as $group) {
				$user->addGroup($group);
			}

			$user->update();
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->addAction();
		}

		\sly_Core::dispatcher()->notify('WV16_USER_ADDED', $user, array('password' => $password1));
		print \sly_Helper_Message::info('Der Benutzer wurde erfolgreich angelegt.');

		$this->indexAction();
	}

	public function editAction() {
		$this->init();

		$id   = sly_request('id', 'int');
		$set  = sly_request('setid', 'int', null);
		$user = User::getInstance($id);
		$func = 'edit';

		if ($set !== null) {
			$sets = $user->getSetIDs(false);

			if (!in_array($set, $sets)) {
				$set = $user->getSetID();
			}
		}
		else {
			$set = $user->getSetID();
		}

		$this->render('users/backend.phtml', compact('user', 'func', 'set'), false);
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
		$set       = sly_request('setid', 'int', null);

		///////////////////////////////////////////////////////////////
		// Passwort und Benutzertyp checken

		try {
			// Wir initialisieren das Objekt jetzt schon, damit wir im catch-Block
			// direkt ein edit-Formular anbieten können.

			$user = Factory::getUserByID($id);

			if ($password1 && $password1 != $password2) {
				throw new \Exception('Die beiden Passwörter sind nicht identisch.');
			}

			Factory::getUserType($userType);
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		\sly_Core::dispatcher()->notify('WV16_USER_PRE_UPDATED', $user);

		///////////////////////////////////////////////////////////////
		// Attribute auslesen und vom Datentyp jeweils verarbeiten lassen

		$activeSet = $user->getSetID();

		if ($set !== null) {
			$sets = $user->getSetIDs(false);

			if (!in_array($set, $sets)) {
				$set = $user->getSetID();
			}
		}
		else {
			$set = $user->getSetID();
		}

		$valuesToStore = $this->serializeForm($userType);

		if ($valuesToStore === null) {
			$errors = $this->errors;

			foreach ($errors as $idx => $e) {
				$errors[$idx] = sly_translate(Factory::getAttribute($e['attribute'])->getTitle()).': '.$e['error'];
			}

			print \sly_Helper_Message::warn(implode('<br />', $errors));
			return $this->editAction();
		}

		$wasActivated = $user->wasEverActivated();
		$oldLogin     = $user->getLogin();

		///////////////////////////////////////////////////////////////
		// Attribute sind OK. Ab in die Datenbank damit.

		try {
			\WV_SQL::getInstance()->beginTransaction();

			$user->setUserType($userType);
			$user->setLogin($login);

			if (!empty($password1)) {
				$user->setPassword($password1);
			}

			// Attributmenge aktualisieren
			$user->update();
			$user->setSetID($set);

			foreach ($valuesToStore as $name => $value) {
				$user->setSerializedValue($name, $value);
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

			\WV_SQL::getInstance()->commit();
		}
		catch (\Exception $e) {
			\WV_SQL::getInstance()->rollBack();
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		// reset the active set
		$user->setSetID($activeSet);

		$params = !empty($password1) ? array('password' => $password1) : array();
		$params['old_login'] = $oldLogin;

		\sly_Core::dispatcher()->notify('WV16_USER_UPDATED', $user, $params);
		print \sly_Helper_Message::info('Der Benutzer wurde erfolgreich bearbeitet.');

		// Bei der ersten Aktivierung benachrichtigen wir den Benutzer.

		$firstTimeActivation = !$wasActivated && $user->wasEverActivated();

		if ($firstTimeActivation) {
			try {
				\WV_UserWorkflows::notifyUserOnActivation($user);
				print \sly_Helper_Message::info('Der Nutzer wurde per Mail über seine Aktivierung benachrichtigt.');
			}
			catch (\Exception $e) {
				print \sly_Helper_Message::warn('Das Senden der Aktivierungsbenachrichtigung schlug fehl: '.sly_html($e->getMessage()).'.');
			}
		}

		$this->indexAction();
	}

	public function deleteAction() {
		$this->init();

		try {
			$id     = sly_request('id', 'int');
			$user   = User::getInstance($id);
			$values = $user->getValues(); // für den EP vor der Vernichtung retten

			$user->delete();
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		\sly_Core::dispatcher()->notify('WV16_USER_DELETED', $user, array('values' => $values));
		print \sly_Helper_Message::info('Der Benutzer wurde erfolgreich gelöscht.');

		$this->indexAction();
	}

	public function checkPermission($action) {
		$user = \sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('frontenduser', 'users'));
	}

	private function serializeForm($userType) {
		$requiredAttrs  = Provider::getAttributes($userType);
		$availableAttrs = Provider::getAttributes();
		$values         = array();

		foreach ($availableAttrs as $name => $attr) {
			// Wir lassen keine Daten zu, die nicht zu diesem Benutzertyp gehören.
			if (!isset($requiredAttrs[$name])) continue;

			try {
				$values[$name] = $attr->serializeForm();
			}
			catch (\Exception $e) {
				$this->errors[] = array(
					'attribute' => $name,
					'error'     => $e->getMessage()
				);
			}
		}

		return empty($this->errors) ? $values : null;
	}

	protected function getAttributesToDisplay($assigned, $required) {
		$available = Provider::getAttributes();
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
