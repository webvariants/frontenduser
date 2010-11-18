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

	protected function checkPermission() {
		return WV_Sally::isAdminOrHasPerm('frontenduser[]');
	}
}
