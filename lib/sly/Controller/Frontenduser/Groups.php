<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser_Groups extends sly_Controller_Frontenduser {
	private $errors = array();

	protected function index() {
		$groups = WV16_Provider::getGroups();
		$this->render('addons/frontenduser/templates/groups/table.phtml', compact('groups'));
	}

	protected function add() {
		$group = null;
		$func  = 'add';
		$this->render('addons/frontenduser/templates/groups/backend.phtml', compact('group', 'func'));
	}

	protected function do_add() {
		$group = null;
		$name  = sly_post('name', 'string');
		$title = sly_post('title', 'string');

		try {
			$group = _WV16_Group::create($name, $title);
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->add();
		}

		sly_Core::dispatcher()->notify('WV16_GROUP_ADDED', $group);
		print rex_info('Die Gruppe wurde erfolgreich angelegt.');

		$this->index();
	}

	protected function edit() {
		$name  = sly_request('name', 'string');
		$group = WV16_Factory::getGroup($name);
		$func  = 'edit';
		$this->render('addons/frontenduser/templates/groups/backend.phtml', compact('group', 'func'));
	}

	protected function do_edit() {
		if (isset($_POST['delete'])) {
			return $this->delete();
		}

		$name  = sly_post('name', 'string');
		$title = sly_post('title', 'string');
		$group = WV16_Factory::getGroup($name);

		try {
			$group->setTitle($title);
			$group->update();
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_GROUP_UPDATED', $group);
		print rex_info('Die Gruppe wurde erfolgreich bearbeitet.');

		$this->index();
	}

	protected function delete() {
		try {
			$name  = sly_post('name', 'string');
			$group = WV16_Factory::getGroup($name);

			$group->delete();
		}
		catch (Exception $e) {
			print rex_warning($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_GROUP_DELETED', $group);
		print rex_info('Die Gruppe wurde erfolgreich gelöscht.');

		$this->index();
	}

	protected function checkPermission() {
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || $user->hasPerm('frontenduser[groups]');
	}
}
