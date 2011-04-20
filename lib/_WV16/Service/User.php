<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class _WV16_Service_User extends WV_Object {
	/**
	 * @return int  die ID des neuen Benutzers
	 */
	public function register($login, $password, $userType = null) {
		$sql      = WV_SQL::getInstance();
		$password = trim($password);
		$login    = trim($login);

		if ($userType === null) {
			$userType = _WV16_UserType::DEFAULT_NAME;
		}

		if (empty($login)) {
			throw new WV16_Exception('Der Login darf nicht leer sein.');
		}

		if ($sql->count('wv16_users', 'LOWER(login) = ?', strtolower($login)) != 0) {
			throw new WV16_Exception('Der Login ist bereits vergeben.');
		}

		_WV16_User::testPassword($password);

		$registered       = date('Y-m-d H:i:s');
		$confirmationCode = WV16_Users::generateConfirmationCode($login);
		$params           = array($login, $password, $userType, $registered, $confirmationCode);

		return self::transactionGuard(array($this, '_register'), $params, 'WV16_Exception');
	}

	protected function _register($login, $password, $userType, $registered, $confirmationCode) {
		$sql = WV_SQL::getInstance();

		$sql->query(
			'INSERT INTO ~wv16_users (login,password,registered,`type`,activated,confirmed,confirmation_code) VALUES (?,"",?,?,?,?,?)',
			array($login, $registered, $userType, 0, 0, $confirmationCode), '~'
		);

		// Passwort sicher hashen

		$userID = $sql->lastID();
		$pwhash = sha1($userID.$password.$registered);

		$sql->query('UPDATE ~wv16_users SET password = ? WHERE id = ?', array($pwhash, $userID), '~');

		// Attribute und ihre Standardwerte übernehmen

		$attributes = WV16_Provider::getAttributes($userType);

		foreach ($attributes as $name => $attr) {
			$sql->query(
				'INSERT INTO ~wv16_user_values (user_id,attribute,set_id,value) VALUES (?,?,?,?)',
				array($userID, $name, 1, $attr->getDefault()), '~'
			);
		}

		// Cache leeren

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.counts', true);
		$cache->flush('frontenduser.lists', true);

		return $userID;
	}

	public function update(_WV16_User $user) {
		self::transactionGuard(array($this, '_update'), $user, 'WV16_Exception');
	}

	protected function _update(_WV16_User $user) {
		$sql   = WV_SQL::getInstance();
		$login = $user->getLogin();
		$id    = $user->getID();

		// Eindeutigkeit prüfen

		if ($sql->count('wv16_users', 'LOWER(login) = ? AND id <> ?', array(strtolower($login), $id)) != 0) {
			throw new WV16_Exception('Der Login ist bereits vergeben.');
		}

		// Bestötigunsgcode neu setzen

		$code      = $user->getConfirmationCode();
		$confirmed = $user->isConfirmed();
		$activated = $user->isActivated();
		$type      = $user->getTypeName();

		if ($code === null) {
			$code = $user->setConfirmationCode($confirmed ? '' : null);
		}

		// Originaltyp abrufen

		$origType = $sql->fetch('`type`', 'wv16_users', 'id = ?', $id);

		// Grunddaten aktualisieren

		$sql->query(
			'UPDATE ~wv16_users SET login = ?, password = ?, `type` = ?, activated = ?, confirmed = ?, confirmation_code = ? WHERE id = ?',
			array($login, $user->getPasswordHash(), $type, (int) $activated, (int) $confirmed, $code, $id), '~'
		);

		// erste Aktivierung merken

		if ($activated && !$user->wasEverActivated()) {
			$sql->query('UPDATE ~wv16_users SET was_activated = 1 WHERE id = ?', $id, '~');
			$user->_setEverActivated();
		}

		// Benutzerdaten aktualisieren

		if ($type !== $origType) {
			$oldTypesAttributes = array_keys(WV16_Provider::getAttributes($origType));
			$newTypesAttributes = array_keys(WV16_Provider::getAttributes($type));

			$toDelete = array_diff($oldTypesAttributes, $newTypesAttributes);
			$toAdd    = array_diff($newTypesAttributes, $oldTypesAttributes);

			if (!empty($toDelete)) {
				$markers = '\''.implode('\',\'', $toDelete).'\'';

				$sql->query(
					'DELETE FROM ~wv16_user_values WHERE user_id = ? AND set_id >= 0 AND attribute IN ('.$markers.')',
					$id, '~'
				);
			}

			foreach ($toAdd as $name) {
				$attribute = WV16_Users::getAttribute($name);
				$default   = $attribute->getDefault();

				$sql->query(
					'INSERT INTO ~wv16_user_values (user_id,set_id,attribute,value) '.
					'SELECT v.user_id,v.set_id,?,? FROM ~wv16_user_values v, ~wv16_users u '.
					'WHERE v.user_id = u.id AND u.id = ? AND v.set_id >= 0',
					array($name, $default) , '~'
				);
			}
		}

		// Cache leeren

		$cache = sly_Core::cache();
		$cache->delete('frontenduser.users', $id);
		$cache->delete('frontenduser.users.firstsets', $id);
		$cache->delete('frontenduser.users.typeids', $id);
		$cache->flush('frontenduser.lists', true);
		$cache->flush('frontenduser.counts', true);
	}

	public function delete(_WV16_User $user) {
		return self::transactionGuard(array($this, '_delete'), $user, 'WV16_Exception');
	}

	protected function _delete(_WV16_User $user) {
		$sql = WV_SQL::getInstance();
		$id  = $user->getID();

		$sql->query('DELETE FROM ~wv16_users WHERE id = ?', $id, '~');
		$sql->query('DELETE FROM ~wv16_user_groups WHERE user_id = ?', $id, '~');
		$sql->query('DELETE FROM ~wv16_user_values WHERE user_id = ?', $id, '~');

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.uservalues', true);
		$cache->flush('frontenduser.lists', true);
		$cache->flush('frontenduser.counts', true);
	}

	/**
	 * @return boolean  true, falls ja, sonst false
	 */
	public function exists($login) {
		$cache     = sly_Core::cache();
		$namespace = 'frontenduser.users';
		$cacheKey  = sly_Cache::generateKey('mapping', $login);

		if ($cache->exists($namespace, $cacheKey)) {
			return true;
		}

		$sql = WV_SQL::getInstance();
		$id  = $sql->fetch('id', 'wv16_users','LOWER(login) = ?', strtolower($login));

		if ($id !== false) {
			$cache->set($namespace, $cacheKey, (int) $id);
			return true;
		}

		return false;
	}

	public function addToGroup(_WV16_User $user, $group) {
		return self::transactionGuard(array($this, '_addToGroup'), array($user, $group), 'WV16_Exception');
	}

	protected function _addToGroup(_WV16_User $user, $group) {
		$sql = WV_SQL::getInstance();
		$id  = $user->getID();

		if (!_WV16_Group::exists($group)) {
			throw new WV16_Exception('Die Gruppe existiert nicht.');
		}

		if ($sql->count('wv16_user_groups', 'user_id = ? AND `group` = ?', array($id, $group)) > 0) {
			return false;
		}

		$sql->query('INSERT INTO ~wv16_user_groups (user_id,`group`) VALUES (?,?)', array($id, $group), '~');

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.lists', true);

		return true;
	}

	public function removeFromGroup(_WV16_User $user, $group) {
		return self::transactionGuard(array($this, '_removeFromGroup'), array($user, $group), 'WV16_Exception');
	}

	protected function _removeFromGroup(_WV16_User $user, $group) {
		$sql   = WV_SQL::getInstance();
		$id    = $user->getID();
		$query = 'DELETE FROM ~wv16_user_groups WHERE user_id = ? AND `group` = ?';

		$sql->query($query, array($id, $group), '~');

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.lists', true);

		return true;
	}

	public function removeFromAllGroups(_WV16_User $user) {
		return self::transactionGuard(array($this, '_removeFromAllGroups'), $user, 'WV16_Exception');
	}

	protected function _removeFromAllGroups(_WV16_User $user) {
		$sql = WV_SQL::getInstance();
		$id  = $user->getID();

		$sql->query('DELETE FROM ~wv16_user_groups WHERE user_id = ?', $id, '~');

		$cache = sly_Core::cache();
		$cache->flush('frontenduser.users', true);
		$cache->flush('frontenduser.lists', true);

		return true;
	}
}
