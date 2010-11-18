<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontenduser_Attributes extends sly_Controller_Frontenduser {
	protected function index() {
		$layout = sly_Core::getLayout();
		$layout->addJavaScriptFile('../data/dyn/public/developer_utils/js/jquery.tablednd.min.js');

		$search = sly_Table::getSearchParameters('attributes');
		$paging = sly_Table::getPagingParameters('attributes', true, false);
		$where  = 'deleted = 0';

		if (!empty($search)) {
			$searchSQL = ' AND (`name` = ? OR `title` = ? OR `params` = ? OR `default_value` = ?)';
			$searchSQL = str_replace('=', 'LIKE', $searchSQL);
			$searchSQL = str_replace('?', '"%'.mysql_real_escape_string($search).'%"', $searchSQL);

			$where .= $searchSQL;
		}

		$attributes = WV16_Users::getAllAttributes($where, 'position', 'asc', $paging['start'], $paging['elements']);
		$total      = WV16_Users::getTotalAttributes($where);

		$this->render('addons/frontenduser/templates/attributes/table.phtml', compact('attributes', 'total'));
	}

	protected function add() {
		$attribute = null;
		$func      = 'add';
		$this->render('addons/frontenduser/templates/attributes/backend.phtml', compact('attribute', 'func'));
	}

	protected function do_add() {
		$attribute = null;
		$name      = sly_post('name',     'string');
		$title     = sly_post('title',    'string');
		$helptext  = sly_post('helptext', 'string');
		$datatype  = sly_post('datatype', 'int');
		$hidden    = sly_post('hidden',   'boolean', false);
		$usertypes = sly_postArray('utypes', 'int');

		try {
			WV_Sally::enforceMethod('POST');

			if (!WV_Datatype::exists($datatype)) {
				throw new WV16_Exception('Der gewählte Datentyp existiert nicht!');
			}

			list($params, $default) = WV_Datatype::call($datatype, 'serializeConfigForm');
			$attribute = _WV16_Attribute::create($name, $title, $helptext, $datatype, $params, $default, $hidden, $usertypes);
		}
		catch (Exception $e) {
			WV_Sally::error($e->getMessage());
			return $this->add();
		}

		sly_Core::dispatcher()->notify('WV16_ATTRIBUTE_ADDED', $attribute);
		WV_Redaxo::success('Das Attribut wurde erfolgreich gespeichert.');

		$this->index();
	}

	protected function edit() {
		$id        = sly_request('id', 'int');
		$attribute = _WV16_Attribute::getInstance($id);
		$func      = 'edit';

		$this->render('addons/frontenduser/templates/attributes/backend.phtml', compact('attribute', 'func'));
	}

	protected function do_edit() {
		if (isset($_POST['delete'])) {
			return $this->delete();
		}

		$attribute     = null;
		$id            = sly_request('id', 'int');
		$name          = sly_post('name',  'string');
		$title         = sly_post('title', 'string');
		$helptext      = sly_post('helptext', 'string');
		$datatype      = sly_post('datatype', 'int');
		$hidden        = sly_post('hidden',       'boolean', false);
		$confirmed     = sly_post('confirmed',    'boolean', false);
		$noconversion  = sly_post('noconversion', 'boolean', false);
		$applyDefaults = sly_post('datatype_'.$datatype.'_applydefault', 'boolean', false);
		$usertypes     = sly_postArray('utypes', 'int');

		try {
			WV_Sally::enforceMethod('POST');

			if (!WV_Datatype::exists($datatype)) {
				throw new WV16_Exception('Der gewählte Datentyp existiert nicht!');
			}

			$attribute = _WV16_Attribute::getInstance($id);

			// VOR dem Update prüfen, ob ein Löschen von Daten notwendig ist. Falls
			// ja, den Benutzer erst fragen, bevor wir die Daten übernehmen.

			if (!_WV16_Attribute::checkCompatibility($confirmed, $attribute, $datatype)) {
				return;
			}

			$attribute->setName($name);
			$attribute->setTitle($title);
			$attribute->setHelpText($helptext);
			$attribute->setHidden($hidden);
			$attribute->setDatatype($datatype);
			$attribute->setUserTypes($usertypes);

			list($params, $default) = WV_Datatype::call($datatype, 'serializeConfigForm');

			$attribute->setParams($params);
			$attribute->setDefaultValue($default);

			$attribute->update(!$noconversion, $applyDefaults);
		}
		catch (Exception $e) {
			WV_Sally::error($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_ATTRIBUTE_UPDATED', $attribute);
		WV_Redaxo::success('Das Attribut wurde erfolgreich gespeichert.');

		$this->index();
	}

	protected function delete() {
		$id        = sly_request('id', 'int');
		$attribute = null;

		try {
			WV_Redaxo::enforceMethod('POST');

			$attribute = _WV16_Attribute::getInstance($id);
			$attribute->delete();
		}
		catch (Exception $e) {
			WV_Sally::error($e->getMessage());
			return $this->edit();
		}

		sly_Core::dispatcher()->notify('WV16_ATTRIBUTE_DELETED', $attribute);
		WV_Sally::success('Das Attribut wurde gelöscht.');

		$this->index();
	}

	protected function shift() {
		$id        = sly_request('id', 'int');
		$position  = sly_get('position', 'int');
		$attribute = null;

		try {
			$attribute = _WV16_Attribute::getInstance($id);
			$attribute->shift($position);
		}
		catch (Exception $e) {
			// pass..
		}

		sly_Core::dispatcher()->notify('WV16_ATTRIBUTE_SHIFTED', $attribute);
		while (ob_get_level()) ob_end_clean();
		die;
	}

	public function checkPermission() {
		return WV_Sally::isAdminOrHasPerm('frontenduser[attributes]');
	}
}
