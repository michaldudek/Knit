<?php

class MDModel
{
    
    /*
     * FACTORY METHODS
     * These can be overwritten.
     */

    /**
     * Parses the sent selection criteria with possibility to define the logic that would join them,
     * i.e. AND or OR between each argument of the function.
     * @return string Parsed SQL WHERE statement.
     * @param string $logic[optional] Logic to be used. 'AND' or 'OR'. Default: 'AND'. Can be ommitted.
     * @param array $criteria[optional] A set of criteria.
     * @param array ... You can send as many criteria as you wish.
     */
    public static function criteria() {
        // get the function's arguments
        $arguments = func_get_args();

        // check if there was a logic set that will be used to join all the arguments together
        $logic = MDLogicAnd;
        if (isset($arguments[0]) AND !is_array($arguments[0]) AND in_array(strtoupper($arguments[0]), array(MDLogicAnd, MDLogicOr))) {
            $logic = $arguments[0];
            array_shift($arguments);
        }

        $db = self::_getDatabase();

        $criteria = array();
        foreach($arguments as $fields) {
            $criteria[] = '('. NL . $db->parseCriteria($fields) . NL .')';
        }

        if (empty($criteria)) {
            return '(1)';
        }

        $criteria = '('. NL . $db->parseCriteria($criteria, $logic) . NL .')';
        return $criteria;
    }

    /**
     * Very similar to MDModel::criteria() except that the order of arguments is different.
     * @return string Parsed SQL WHERE statement.
     * @param array $criteria A set of criteria to be joined using the specified logic.
     * @param string $logic[optional] Logic to be used. 'AND' or 'OR'. Default: 'AND'.
     */
    public static function where($criteria, $logic = 'AND') {
        $arguments = array($logic);
        foreach($criteria as $criterium) {
            $arguments[] = $criterium;
        }

        $where = call_user_func_array(array(get_called_class(), 'criteria'), $arguments);
        return $where;
    }
    
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