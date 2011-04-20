<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Service_Value extends WV_Object {
	public function read(_WV16_User $user, $setID = null, $raw = false) {
		$setID  = $setID === null ? $user->getSetID() : (int) $setID;
		$sql    = WV_SQL::getInstance();
		$qry    = 'SELECT attribute, value FROM ~wv16_user_values WHERE user_id = ? AND set_id = ?';
		$data   = $sql->getArray($qry, array($setID, $user->getID()), '~');
		$result = array();

		if ($raw) {
			return $data;
		}

		foreach ($data as $attr => $rawValue) {
			$result[$attr] = WV16_Factory::getAttribute($attr)->deserialize($rawValue);
		}

		return $result;
	}

	public function write(_WV16_User $user, $setID, $attribute, $value) {
		$setID = $setID === null ? $user->getSetID() : (int) $setID;

		// read-only?

		if ($user->isDeleted() || _WV16_Service_Set::isReadOnlySet($setID)) {
			return false;
		}

		$params = array($user, $setID, $attribute, strval($value));
		return self::transactionGuard(array($this, '_write'), $params, 'WV16_Exception');
	}

	protected function _write(_WV16_User $user, $setID, $attribute, $value) {
		$sql = WV_SQL::getInstance();

		$sql->query(
			'UPDATE ~wv16_user_values SET value = ? WHERE user_id = ? AND set_id = ? AND attribute = ?',
			array($value, $user->getID(), $setID, $attribute), '~'
		);

		return $sql->affectedRows() > 0;
	}
}
