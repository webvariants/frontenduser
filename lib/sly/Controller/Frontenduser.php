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
	protected function init() {
		$pages = array(
			''           => 'Benutzer',
//			'groups'     => 'Gruppen',
			'types'      => 'Benutzertypen',
			'attributes' => 'Attribute'
		);

		foreach ($pages as $key => $value) {
			if (WV_Sally::isAdminOrHasPerm('frontenduser['.$key.']')) {
				$subpages[] = array($key, $value);
			}
		}

		sly_Core::getNavigation()->get('frontenduser', 'addon')->addSubpages($subpages);
		sly_Core::getLayout()->pageHeader(t('frontenduser_title'), $subpages);
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

		$valuesToStore = _WV16_FrontendUser::serializeUserForm($userType);

		if ($valuesToStore === null) {
			$errors = _WV16_FrontendUser::getErrors();

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

	protected function checkPermission() {
		return WV_Sally::isAdminOrHasPerm('frontenduser[]');
	}
}
