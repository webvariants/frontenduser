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

class _WV16_UserValue
{
	protected $serializedValue; ///< string          der noch serialisierte Wert
	protected $value;           ///< mixed           der vom Datentyp deserialisierte Wert
	protected $attribute;       ///< _WV16_Attribute das Attribut
	protected $user;            ///< mixed           der Benutzer
	
	/**
	 * Konstruktor
	 * 
	 * Im Konstruktor wird das Metadatum erzeugt und der Wert aus der Datenbank
	 * mittels des Datentyps deserialisiert. Sollte für das Objekt ($object)
	 * eine ID (int) übergeben werden, so wird anhand des $type-Parameters das
	 * Objekt erst in der Methode erzeugt.
	 * 
	 * @param string        $value    der noch serialisierte Wert
	 * @param _WV2_MetaInfo $metainfo die Metainformation
	 * @param mixed         $user     der dazugehörige Benutzer
	 */
	public function __construct($value, $attribute, $user)
	{
		$this->serializedValue = $value;
		$this->attribute       = null;
		$this->user            = null;
		$this->value           = $value;
		
		if ($attribute != null) {
			$this->user      = is_int($user) ? _WV16_User::getInstance($user) : $user;
			$this->attribute = $attribute instanceof _WV16_Attribute ? $attribute : _WV16_Attribute::getInstance($attribute);
			
			// Wert über den Datentyp automatisch deserialisieren
			
			$this->value = WV_Datatype::call(
				$this->attribute->getDatatypeID(),
				'deserializeValue',
				array($value, $this->attribute->getParams()));
		}
	}
	
	/*@{*/
	
	/**
	 * Getter
	 * 
	 * Diese Methode gibt die entsprechende Eigenschaft ungefiltert zurück.
	 * 
	 * @return mixed  die entsprechende Eigenschaft
	 */
	public function getCLang()           { return $this->clang;           }
	public function getUser()            { return $this->user;            }
	public function getAttribute()       { return $this->attribute;       }
	public function getSerializedValue() { return $this->serializedValue; }
	public function getAttribute()       { return $this->attribute ? $this->attribute                : null; }
	public function getAttributeID()     { return $this->attribute ? $this->attribute->getID()       : null; }
	public function getAttributeName()   { return $this->attribute ? $this->attribute->getName()     : null; }
	public function getDatatype()        { return $this->attribute ? $this->attribute->getDatatype() : null; }
	
	/*@}*/

	/**
	 * Wert ermitteln
	 * 
	 * Diese Methode gibt den Wert des Metadatums zurück. Dies ist der bereits
	 * deserialisierte, den man als Nutzer vom Datentyp auch erwartet (bei
	 * _WV2_Select z.B. ein array).
	 * 
	 * Wird $element mit einem Wert ungleich -1 belegt, so wird, falls der
	 * Wert ein Array ist, das n-te Element aus diesem Array zurückgegeben. Ist
	 * der Wert kein Array, wird die Angabe $element ignoriert.
	 * 
	 * @param  int   $element  (optional) der gewünschte Index im Wert (falls Array)
	 * @return mixed           der entsprechende Wert
	 */
	public function getValue($element = -1)
	{
		if ($element == -1 || !is_array($this->value)) return $this->value;
		if ($element < 0) $element = 0;
		if ($element > count($this->value)) $element = count($this->value) - 1;
		$values = array_values($this->value);
		if (empty($values)) return null;
		return $values[$element];
	}
	
	/**
	 * Schlüsselwert ermitteln
	 * 
	 * Diese Methode macht nur für Werte Sinn, die als Array repräsentiert
	 * werden. Über sie kann wie bei getValue() mit einem Index auf den n-ten
	 * Schlüssel im Array zugegriffen werden. Damit muss der Nutzer nicht erst
	 * per getValue() das gesamte Array holen, array_keys anwenden und dann das
	 * n-te Element auslesen.
	 * 
	 * Ist der Wert kein Array, so wird die Methode den Wert direkt zurückgeben.
	 * 
	 * @param  int   $element  der gewünschte Index im Wert (falls Array)
	 * @return mixed           der entsprechende Schlüsselwert
	 */
	public function getKey($element = 0)
	{
		if (!is_array($this->value)) return $this->value;
		if ($element < 0) $element = 0;
		if ($element > count($this->value)) $element = count($this->value) - 1;
		$keys = array_keys($this->value);
		if (empty($keys)) return null;
		return $keys[$element];
	}
	
	/**
	 * Schlüsselwerte ermitteln
	 * 
	 * Diese Methode liefert für einen Arraywert alle Schlüssel zurück. Für einen
	 * Nicht-Array-Wert wird der Wert als Array zurückgegeben (array(value)).
	 * 
	 * @return array  Liste aller Schlüssel bzw. einelementige Liste mit dem einzigen Wert
	 */
	public function getKeys()
	{
		if (!is_array($this->value)) return array($this->value);
		return array_keys($this->value);
	}
}
