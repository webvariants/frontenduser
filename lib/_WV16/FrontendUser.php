<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

abstract class _WV16_FrontendUser {
	const DEFAULT_USER_TYPE = 1;

	const TYPE_ARTICLE  = 1;
	const TYPE_CATEGORY = 2;
	const TYPE_MEDIUM   = 3;

	private static $errors = null;

	public static function getIDForUserType($userType, $allowNull = true) {
		if ($userType === null && $allowNull) return null;
		if ($userType instanceof _WV6_UserType) return $userType->getID();
		if (sly_Util_String::isInteger($userType)) return (int) $userType;
		else return _WV16_UserType::getIDForName($userType);
	}

	public static function getIDForUser($user, $allowNull = true) {
		if ($user === null && $allowNull) return null;
		if ($user instanceof _WV16_User) return $user->getID();
		return (int) $user;
	}

	public static function getIDForGroup($group, $allowNull = true) {
		if ($group === null && $allowNull) return null;
		if ($group instanceof _WV6_Group) return $group->getID();
		return _WV16_Group::getIDForName($group);
	}

	public static function getIDForAttribute($attribute, $allowNull = true) {
		if ($attribute === null && $allowNull) return null; /* <- bedeutet im DataProvider: "gib mir alle Attribute!" */
		if (sly_Util_String::isInteger($attribute)) return (int) $attribute;
		if (is_string($attribute)) return _WV16_Attribute::getIDForName($attribute);
		if ($attribute instanceof _WV16_Attribute) return (int) $attribute->getID();
		if ($attribute instanceof _WV16_UserValue) return (int) $attribute->getAttributeID();
		trigger_error('Konnte Attribute-ID für "'.$attribute.'" ('.gettype($attribute).') nicht ermitteln!', E_USER_WARNING);
		return -1;
	}

	public static function serializeUserForm($userType) {
		$requiredAttrs  = WV16_Users::getAttributesForUserType($userType);
		$availableAttrs = WV16_Users::getAttributesForUserType(-1);
		$valuesToStore  = array();
		$errors         = array();

		foreach ($availableAttrs as $attr) {
			$isRequired = false;

			foreach ($requiredAttrs as $rattr) {
				if ($rattr->getID() == $attr->getID()) {
					$isRequired = true;
					break;
				}
			}

			// Wir lassen keine Daten zu, die nicht zu diesem Benutzertyp gehören.

			if (!$isRequired) {
				continue;
			}

			try {
				$inputForUser = WV_Datatype::call($attr->getDatatypeID(), 'serializeFrontendForm', array($attr->getParams(), $attr));

				// Keine gültige Eingabe aber benötigtes Feld? -> Abbruch!

				if ($inputForUser === false && $isRequired) {
					$errors[] = array(
						'attribute' => $attr->getID(),
						'error'     => 'Diese Angabe ist ein Pflichtfeld!'
					);
					continue;
				}

				// Woohoo! Eine Eingabe! Die merken wir uns.

				$valuesToStore[] = array(
					'value'     => $inputForUser,
					'attribute' => $attr->getID()
				);
			}
			catch (WV_DatatypeException $e) {
				$errors[] = array(
					'attribute' => $attr->getID(),
					'error'     => $e->getMessage()
				);
			}
		}

		if (empty($errors)) return $valuesToStore;

		self::$errors = $errors;
		return null;
	}

	public static function getErrors() {
		return self::$errors;
	}

	public static function getAttributesToDisplay($available, $assigned, $required) {
		$return = array();

		foreach ($available as $attribute) {
			$metadata = null;
			$req      = false;

			foreach ($assigned as $data) {
				if ($data->getAttributeID() == $attribute->getID()) {
					$metadata = $data;
					break;
				}
			}

			foreach ($required as $rinfo) {
				if ($rinfo->getID() == $attribute->getID()) {
					$req = true;
					break;
				}
			}

			$return[] = array('attribute' => $attribute, 'data' => $metadata, 'required' => $req);
		}

		return $return;
	}

	/**
	 * POST-Daten ausgeben
	 *
	 * Diese Methode erzeugt versteckte Formular-Elemente fÃ¼r alle Daten im
	 * superglobalen Array $_POST. Damit wird das erneute Versenden eines
	 * Formulars möglich.
	 */
	public static function printPOSTData() {
		foreach ($_POST as $key => $value) {
			if (!is_array($value)) {
				print '<input type="hidden" name="'.sly_html($key).'" value="'.sly_html($value).'" />';
			}
			else {
				foreach ($value as $v) {
					print '<input type="hidden" name="'.sly_html($key).'[]" value="'.sly_html($v).'" />';
				}
			}
		}
	}
}
