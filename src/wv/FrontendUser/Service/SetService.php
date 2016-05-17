<?php
/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace wv\FrontendUser\Service;

use wv\FrontendUser\Exception;
use wv\FrontendUser\User;

class SetService extends \WV_Object {
	public function getSetIDs(User $user, $includeReadOnly = false) {
		$cache     = \sly_Core::cache();
		$namespace = 'frontenduser.lists';
		$id        = $user->getID();
		$cacheKey  = sha1(json_encode(array('set_ids', $id, $includeReadOnly)));
		$ids       = $cache->get($namespace, $cacheKey, null);

		if (is_array($ids)) {
			return $ids;
		}

		$includeReadOnly = $includeReadOnly ? '' : ' AND set_id >= 0';

		$ids = \WV_SQL::getInstance()->getArray(
			'SELECT DISTINCT set_id FROM ~wv16_user_values WHERE user_id = ?'.$includeReadOnly.' ORDER BY set_id',
			$id, '~'
		);

		$ids = array_map('intval', $ids);
		$cache->set($namespace, $cacheKey, $ids);

		return $ids;
	}

	public function getFirstSetID(User $user) {
		$cache      = \sly_Core::cache();
		$userID     = $user->getID();
		$namespace  = 'frontenduser.users.firstsets';
		$firstSetID = $cache->get($namespace, $userID, null);

		if ($firstSetID === null) {
			$sql = \WV_SQL::getInstance();
			$id  = $sql->fetch('MIN(set_id)', 'wv16_user_values', 'user_id = ? AND set_id >= 0', $userID);

			// Die kleinste erlaubte ID ist 1. Wenn noch keine Werte vorhanden sein
			// sollten, müssen wir dies hier dennoch sicherstellen.

			$firstSetID = $id == 0 ? 1 : (int) $id;
			$cache->set($namespace, $userID, $firstSetID);
		}

		return $firstSetID;
	}

	public function createSetCopy(User $user, $sourceSetID = null, $empty = false) {
		$setID = $sourceSetID === null ? $this->getFirstSetID($user) : (int) $sourceSetID;
		$id    = $user->getID();
		$newID = \WV_SQL::getInstance()->fetch('MAX(set_id)', 'wv16_user_values', 'user_id = ?', $id) + 1;

		$this->copySet($user, $setID, $newID, $empty);
		return $newID;
	}

	public function createReadOnlySet(User $user, $sourceSetID = null) {
		$setID = $sourceSetID === null ? $this->getFirstSetID($user) : (int) $sourceSetID;
		$id    = $user->getID();
		$newID = \WV_SQL::getInstance()->fetch('MIN(set_id)', 'wv16_user_values', 'user_id = ?', $id) - 1;

		// Ab Version 1.2.1 sind die Standard-IDs >= 0. Um Konflikten aus dem Weg zu gehen, wenn alte
		// Daten aktualisiert werden, stellen wir hier sicher, dass die erste ReadOnly-ID garantiert
		// kleiner als 0 ist.

		if ($newID == 0) {
			$newID = -1;
		}

		$this->copySet($user, $setID, $newID);
		return $newID;
	}

	public static function isReadOnlySet($setID) {
		return $setID < 0;
	}

	public function deleteSet(User $user, $setID = null) {
		$setID = $setID === null ? $this->getFirstSetID($user) : (int) $setID;

		if (self::isReadOnlySet($setID)) {
			throw new Exception('Schreibgeschützte Sets können nicht gelöscht werden.');
		}

		$sql    = \WV_SQL::getInstance();
		$id     = $user->getID();
		$params = array($id, $setID);

		$sql->query('DELETE FROM ~wv16_user_values WHERE user_id = ? AND set_id = ?', $params, '~');

		$cache = \sly_Core::cache();
		$cache->delete('frontenduser.users', $id);
		$cache->delete('frontenduser.users.firstsets', $id);
		$cache->flush('frontenduser.lists', true);
		$cache->flush('frontenduser.counts', true);

		return $sql->affectedRows() > 0;
	}

	protected function copySet(User $user, $sourceSet, $targetSet, $empty = false) {
		$userID = $user->getID();

		$params = !$empty ? array($targetSet, $userID, $sourceSet) : array($targetSet, '', $userID, $sourceSet);

		\WV_SQL::getInstance()->query(
			'INSERT INTO ~wv16_user_values '.
			'SELECT user_id,attribute,?,'.(!$empty?'value':'?').' FROM ~wv16_user_values WHERE user_id = ? AND set_id = ?',
			$params, '~'
		);

		$cache = \sly_Core::cache();
		$cache->flush('frontenduser.lists', true);
		$cache->delete('frontenduser.users.firstsets', $userID);
	}
}
