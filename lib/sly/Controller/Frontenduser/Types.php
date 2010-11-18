<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser_Types extends sly_Controller_Frontenduser {
	protected function index() {
		$types = WV16_Users::getAllUserTypes('1', 'title', 'ASC');
		$total = WV16_Users::getTotalUserTypes('1');

		$this->render('addons/frontenduser/templates/types/table.phtml', compact('types', 'total'));
	}

	protected function add() {
		$type = null;
		$func = 'add';
		$this->render('addons/frontenduser/templates/types/backend.phtml', compact('type', 'func'));
	}

	protected function do_add() {
		$type       = null;
		$name       = sly_post('name',  'string');
		$title      = sly_post('title', 'string');
		$attributes = sly_postArray('attributes', 'int');

		try {
			WV_Sally::enforceMethod('POST');
			$type = _WV16_UserType::create($name, $title, $attributes);
		}
		catch (Exception $e) {
			WV_Sally::error($e->getMessage());
			return $this->add();
		}

		sly_Core::dispatcher()->notify('WV16_USERTYPE_ADDED', $type);
		WV_Redaxo::success('Der Benutzertyp wurde erfolgreich gespeichert.');

		$this->index();
	}

	protected function edit() {
		$id   = sly_request('id', 'int');
		$type = _WV16_UserType::getInstance($id);
		$func = 'edit';

		$this->render('addons/frontenduser/templates/types/backend.phtml', compact('type', 'func'));
	}

	protected function do_edit() {
		if (isset($_POST['delete'])) {
			return $this->delete();
		}

		$id         = sly_request('id', 'int');
		$type       = null;
		$name       = sly_post('name',  'string');
		$title      = sly_post('title', 'string');
		$attributes = sly_postArray('attributes', 'int');

		try {
			WV_Sally::enforceMethod('POST');

			$type = _WV16_UserType::getInstance($id);

			$type->setName($name);
			$type->setTitle($title);
			$type->setAttributes($attributes);
			$type->update();
		}
		catch (Exception $e) {
			WV_Sally::error($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_USERTYPE_UPDATED', $type);
		WV_Redaxo::success('Der Benutzertyp wurde erfolgreich gespeichert.');

		$this->index();
	}

	protected function delete() {
		$id   = sly_request('id', 'int');
		$type = null;

		try {
			WV_Sally::enforceMethod('POST');

			$type = _WV16_UserType::getInstance($id);
			$type->delete();
		}
		catch (Exception $e) {
			WV_Sally::error($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_USERTYPE_DELETED', $type);
		WV_Sally::success('Der Benutzertyp wurde gelöscht.');

		$this->index();
	}

	public function checkPermission() {
		return WV_Sally::isAdminOrHasPerm('frontenduser[types]');
	}
}