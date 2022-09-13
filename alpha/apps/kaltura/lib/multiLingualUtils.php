<?php

class multiLingualUtils
{
	const MULTI = 'multi';
	const MULTI_LINGUAL = 'multiLingual';
	const DEFAULT_LANGUAGE = 'defaultLanguage';
	const DEFAULT_VALUE = 'defaultValue';
	
	/**
	 * Returns an array containing the default value of the field and the default language of the object.
	 * If the db fields should not be updated, return null in both cells
	 *
	 * @param $object
	 * @param $field
	 * @param $newMapping
	 * @return string[]
	 * @throws KalturaAPIException
	 */
	public static function getFieldDefaultValuesFromNewMapping ($object, $field, $newMapping)
	{
		if (!is_array($newMapping))
		{
			throw new KalturaAPIException(KalturaErrors::INVALID_FIELD_VALUE, $field);
		}
		$result = array(self::DEFAULT_LANGUAGE => '', self::DEFAULT_VALUE => '');
		$defaultLanguage = self::getDefaultLangFromNewMapping($object, $newMapping);
		
		$result[self::DEFAULT_LANGUAGE] = $defaultLanguage;
		$defaultValue = self::getDefaultValueFromNewMapping($newMapping, $field, $defaultLanguage, $object);
		$result[self::DEFAULT_VALUE] = $object->alignFieldValue($field, $defaultValue);
		
		return $result;
	}
	
	/**
	 * remove from the new mapping the values that should be set in the db fields, and set the adjusted mapping
	 * in the object's custom data field
	 * Sets the default language of the object if applicable
	 *
	 * @param $object multilingual supported object
	 * @param string $field multilingual supported field
	 * @param array $newMultiLingualMapping new mapping received in the api call
	 * @param array $defaultValues holds the default language and value for the current field
	 */
	public static function updateMultiLanguageObject(&$object, $field, $newMultiLingualMapping, $defaultValues)
	{
		if ($defaultValues[self::DEFAULT_LANGUAGE])
		{
			self::setDefaultLanguage($object, $defaultValues[self::DEFAULT_LANGUAGE]);
		}
		unset($newMultiLingualMapping[$defaultValues[self::DEFAULT_LANGUAGE]]); // removes default value from newMapping to eliminate duplicity
		
		$currentMultiLingualMapping = json_decode(self::getMultiLanguageMapping($object), true);
		self::addFieldMappingToMultiLangMapping($currentMultiLingualMapping, $field, $newMultiLingualMapping, $object);
		$currentMultiLingualMapping = $currentMultiLingualMapping ? json_encode($currentMultiLingualMapping) : null;
		self::setMultiLanguageMapping($object, $currentMultiLingualMapping);
	}
	
	/**
	 * Returns the language that should be considered as the default language for the object
	 *
	 * @return string default language for the object, or null if the call does not support multi-lingual strings
	 *
	 * @param $object multilingual supported object
	 * @param array $newMapping new mapping received in the api call
	 * @param string $contextLanguage the language of the call
	 **/
	protected static function getDefaultLangFromNewMapping($object, $newMultiLingualMapping)
	{
		if (is_null($newMultiLingualMapping) | count($newMultiLingualMapping) == 0)
		{
			return null;
		}
		$contextLanguage = kCurrentContext::getLanguage();
		$currentMultiLingualMapping = json_decode(self::getMultiLanguageMapping($object), true);
		$languageOfFirstItem = array_keys($newMultiLingualMapping)[0];
		if (strtolower($contextLanguage) === self::MULTI)
		{
			if($languageOfFirstItem)
			{
				return $languageOfFirstItem === 'default' ? self::getDefaultLanguage($object) : $languageOfFirstItem;
			}
			else
			{
				return null;
			}
		}
		else  // A specific language was given in the call
		{
			if($languageOfFirstItem !== 'default')
			{
				throw new KalturaAPIException('Language must be set to MULTI when adding MultiLingualString');
			}
			$defaultLanguage = $contextLanguage;
			if (!self::getDefaultLanguage($object) || !$currentMultiLingualMapping)
			{
				return $defaultLanguage;
			}
			return null;
		}
	}
	
	// Returns the default field value from the mapping or null if mapping does not contain a default value
	protected static function getDefaultValueFromNewMapping($newMultiLingualMapping, $field, $defaultLanguage, $object)
	{
		$value = $newMultiLingualMapping[$defaultLanguage];
		$currentMultiLingualMapping = json_decode(self::getMultiLanguageMapping($object), true);
		
		if(!$value) // new mapping does not contain value in default language or input was a single string converted into array
		{
			if (!isset($currentMultiLingualMapping[$field]) ||
				kCurrentContext::$language === self::getDefaultLanguage($object) ||
				(strtolower(kCurrentContext::$language) === self::MULTI && isset($newMultiLingualMapping['default'])))
			{ // input was a single string converted into array and needs to be set as the default value in the db field
				return $newMultiLingualMapping['default'];
			}
			// the default in the db field should not be changed
			return null;
		}
		
		return $value;
	}
	
	public static function addFieldMappingToMultiLangMapping(&$multiLingualMapping, $field, $valueToAdd, $object)
	{
		if (self::isValueInNewLanguage($object, $valueToAdd)) // add the new value to the mapping  by mapping it to the context language
		{
			$multiLingualMapping[$field][kCurrentContext::$language] = $valueToAdd['default'];
		}
		elseif(!isset($valueToAdd['default']))
		{
			foreach ($valueToAdd as $languageKey => $languageValue)
			{
				if ($multiLingualMapping[$field][$languageKey] != $languageValue)
				{
					$multiLingualMapping[$field][$languageKey] = $languageValue;
				}
			}
		}
	}
	
	protected static function isValueInNewLanguage($object, $value)
	{
		$currentMultiLangMapping = json_decode(self::getMultiLanguageMapping($object), true);
		if (!empty($value) && isset($value['default'])) // add the new value mapped with the lang to the mapping
		{
			if ($currentMultiLangMapping &&
				kCurrentContext::$language !== self::getDefaultLanguage($object) &&
				strtolower(kCurrentContext::$language) !== self::MULTI)
			{
				return true;
			}
			return false;
		}
	}
	
	public static function getFieldValueByLanguage($newMultiLingualMapping, $field, $language)
	{
		if (!isset($newMultiLingualMapping[$field][$language]))
		{
			return null;
		}
		return $newMultiLingualMapping[$field][$language];
	}
	
	public static function getDefaultLanguage($object)
	{
		return $object->getFromCustomData(self::DEFAULT_LANGUAGE, null, null);
	}
	
	public static function setDefaultLanguage(&$object, $value)
	{
		$object->putInCustomData(self::DEFAULT_LANGUAGE, $value);
	}
	
	public static function getMultiLanguageMapping($object)
	{
		return $object->getFromCustomData(self::MULTI_LINGUAL, null, null);
	}
	
	public static function setMultiLanguageMapping(&$object, $value)
	{
		$object->putInCustomData(self::MULTI_LINGUAL, $value);
	}
	
	public static function setCorrectLanguageValuesInResponse(&$responseObject, $dbObject, $requestLanguage = null)
	{
		$multiLanguageMap = json_decode(self::getMultiLanguageMapping($dbObject), true);
		if (strtolower($requestLanguage) === self::MULTI)
		{
			
			self::setMultiLanguageStringInField($responseObject, $dbObject, $multiLanguageMap);
		}
		else
		{
			self::setRequestedLanguageStringInField($responseObject, $dbObject, $multiLanguageMap, $requestLanguage);
		}
	}
	
	protected static function setMultiLanguageStringInField(&$responseObject, $dbObject, $multiLanguageMap)
	{
		if(!$multiLanguageMap)
		{
			return;
		}
		$defaultLanguage = self::getDefaultLanguage($dbObject);
		$supportedFields = $dbObject->getMultiLingualSupportedFields();
		foreach ($supportedFields as $fieldName)
		{
			if ($responseObject->$fieldName)
			{
				$multiLanguageMap[$fieldName][$defaultLanguage] = $dbObject->getDefaultFieldValue($fieldName);
			}
			$responseObject->$fieldName = KalturaMultiLingualStringArray::fromMultiLingualStringArray($multiLanguageMap[$fieldName]);
		}
	}
	
	protected static function setRequestedLanguageStringInField(&$responseObject, $dbObject, $newMultiLingualMapping, $requestLanguage = null)
	{
		$language = $requestLanguage ? $requestLanguage : self::getDefaultLanguage($dbObject);
		$supportedFields = $dbObject->getMultiLingualSupportedFields();
		$supportedFieldsInRequestedLang = array();
		foreach ($supportedFields as $fieldName)
		{
			$supportedFieldsInRequestedLang[$fieldName] = $dbObject->alignFieldValue($fieldName, self::getFieldValueByLanguage($newMultiLingualMapping, $fieldName, $language));
			
			$responseObject->$fieldName = ($supportedFieldsInRequestedLang[$fieldName] && $supportedFieldsInRequestedLang[$fieldName] !== '') ?
				$supportedFieldsInRequestedLang[$fieldName] : $dbObject->getDefaultFieldValue($fieldName);
		}
	}
	
	/**
	 * @param $params
	 * @return array(bool,array)
	 * If needed this function fixes the params and returns a boolean if they should be re deserialized or not
	 */
	public static function shouldResetParamsAndDeserialize($params)
	{
		$skipDeserializer = true;
		foreach ($params as $key => $param)
		{
			foreach ($param as $fieldName => $value)
			{
				if (StringHelper::startsWith(self::MULTI_LINGUAL . '_', $fieldName))
				{
					$newFieldName = substr($fieldName, strrpos($fieldName, '_') + 1);
					$params[$key][$newFieldName] = $param[self::MULTI_LINGUAL . '_' . $newFieldName];
					$skipDeserializer = false;
				}
			}
		}
		return array('skipDeserializer' => $skipDeserializer, 'params' => $params);
	}
	
	public static function isMultiLingualRequest($newMapping)
	{
		if(!isset($newMapping['default']) && !kCurrentContext::$language)
		{
			throw new KalturaAPIException('Language must be set to MULTI when adding MultiLingualString');
		}
		return isset(kCurrentContext::$language);
	}
	
	public static function getMultiLingualStringArrayFromString($value)
	{
		$multiLangString = new KalturaMultiLingualString();
		$multiLangString->language = 'default';
		$multiLangString->value = $value;
		$multiLangStringArr = new KalturaMultiLingualStringArray();
		return $multiLangStringArr->fromDbArray(array($multiLangString->language => $multiLangString->value));
	}
}