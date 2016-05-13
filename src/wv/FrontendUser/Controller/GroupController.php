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
use wv\FrontendUser\Group;
use wv\FrontendUser\Provider;

class GroupController extends BaseController {
	public function indexAction() {
		$this->init();

		$groups = Provider::getGroups();
		$this->render('groups/table.phtml', compact('groups'), false);
	}

	public function addAction() {
		$this->init();

		$group = null;
		$func  = 'add';

		$this->render('groups/backend.phtml', compact('group', 'func'), false);
	}

	public function do_addAction() {
		$this->init();

		$group = null;
		$name  = sly_post('name', 'string');
		$title = sly_post('title', 'string');

		try {
			$group = Group::create($name, $title);
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->addAction();
		}

		\sly_Core::dispatcher()->notify('WV16_GROUP_ADDED', $group);
		print \sly_Helper_Message::info('Die Gruppe wurde erfolgreich angelegt.');

		$this->indexAction();
	}

	public function editAction() {
		$this->init();

		$name  = sly_request('name', 'string');
		$group = Factory::getGroup($name);
		$func  = 'edit';

		$this->render('groups/backend.phtml', compact('group', 'func'), false);
	}

	public function do_editAction() {
		$this->init();

		if (isset($_POST['delete'])) {
			return $this->deleteAction();
		}

		$name  = sly_post('name', 'string');
		$title = sly_post('title', 'string');
		$group = Factory::getGroup($name);

		try {
			$group->setTitle($title);
			$group->update();
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		\sly_Core::dispatcher()->notify('WV16_GROUP_UPDATED', $group);
		print \sly_Helper_Message::info('Die Gruppe wurde erfolgreich bearbeitet.');

		$this->indexAction();
	}

	public function deleteAction() {
		$this->init();

		try {
			$name  = sly_post('name', 'string');
			$group = Factory::getGroup($name);

			$group->delete();
		}
		catch (\Exception $e) {
			print \sly_Helper_Message::warn($e->getMessage());
			return $this->editAction();
		}

		\sly_Core::dispatcher()->notify('WV16_GROUP_DELETED', $group);
		print \sly_Helper_Message::info('Die Gruppe wurde erfolgreich gelöscht.');

		$this->indexAction();
	}

	public function checkPermission($action) {
		$user = \sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('frontenduser', 'groups'));
	}
}
