<?php

class MDModel
{
    
    /*
     * FACTORY METHODS
     * These can be overwritten.
     */
    /*
     * SYSTEM METHODS
     */
    /**
     * Tries to validate the given value against the property definition.
     * @return mixed (bool)true on success or a string on error.
     * @param string $var Name of the variable.
     * @param mixed $value Value to be validated.
     */
    final public function _validateProperty($var, $value) {
        // get info about this property
        $modelInfo = static::_getModelInfo();
        $fieldInfo = $modelInfo['fields'][$var];
        
        // is there is no field info for this $var then it means we were expecting a custom validator
        // trigger a notice but also return (bool)true to let it pass as we don't know how to validate it
        if (!isset($fieldInfo)) {
            $validatorName = 'validate'. ucfirst($var);
            trigger_error('MDForm was expecting the model '. get_called_class() .' to implement validator: '. $validatorName .'. None found', E_USER_NOTICE);
            return true;
        }
        
        // if the field is required then let's check if it's filled
        $checkValue = trim($value);
        if ($fieldInfo['required'] AND (empty($checkValue))) {
            return MDValidationError_Empty;
        }
        
        // if the field has a maxlength then let's check if it exceeds it
        if ($fieldInfo['maxlength'] AND (strlen($value) > $fieldInfo['maxlength'])) {
            return MDValidationError_TooLong;
        }
        
        return true;
    }
    
}