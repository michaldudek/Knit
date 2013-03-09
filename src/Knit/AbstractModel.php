<?php
/**
 * Abstract Model.
 * 
 * Contains object methods for Knit model.
 * 
 * @package Knit
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit;

use Splot\Foundation\Debug\Debugger;
use Splot\Foundation\Debug\Interfaces\Dumpable;
use Splot\Foundation\Utils\ArrayUtils;
use Splot\Foundation\Utils\StringUtils;
use Splot\Foundation\Utils\ObjectUtils;

use Knit\AbstractModelRepository;

abstract class AbstractModel extends AbstractModelRepository implements Dumpable
{
    
    protected $_properties = array();

    /*
     * PERSISTENCY
     */
    /**
     * Will save the model to a persistent storage.
     */
    public function save() {
        if ($this->_getId()) {
            $this->update();
        } else {
            $this->add();
        }
    }
    
    /**
     * Will insert this model as a new instance in persistent storage.
     */
    public function add() {
        $store = static::_getStore();
        
        $id = $db->add(static::$_table, $this->_getPropertiesForStorage());
        $this->_setId($id);
    }
    
    /**
     * Will update this model in the persistent storage.
     */
    public function update() {
        $store = static::_getStore();
        $idProperty = static::$_idProperty;
        
        $store->update(static::$_table, $this->_getPropertiesForStorage(), array(
            $idProperty => $this->_getId()
        ));
    }
    
    /**
     * Will remove this model from the persistent storage and destroy the instance.
     */
    public function delete() {
        $store = static::_getStore();
        $idProperty = static::$_idProperty;

        $store->delete(static::$_table, array(
            $idProperty => $this->_getId()
        ));
        
        unset($this); // destroy yourself
    }

    /*
     * LIFECYCLE EVENTS
     */
    /**
     * This method is called when instantiating a model object before any data is passed to that object.
     * 
     * You should overwrite this method if you want to set any default values of the object.
     */
    protected function willCreateObject() {}
    
    /**
     * This method is called when instantiating a model object after all data have been passed to that object.
     * 
     * You should overwrite this method if you want to set any automatic values that depend on previously passed properties.
     */
    protected function didCreateObject() {}

    /*
     * MAPPING
     */
    /**
     * Updates the model object with data sent in the array.
     * 
     * @param array $data Updated variables.
     * @param bool $autosave [optional] Should the model be autosaved to persistent storage? Default: true.
     */
    public function updateWithData(array $data, $autosave = true) {
        // prevent updating the main key tho!
        $idProperty = static::$_idProperty;
        if (isset($data[$idProperty])) {
            unset($data[$idProperty]);
        }
        
        foreach($data as $var => $value) {
            $setterName = static::_getAccessorName($var, 'set');
            $this->$setterName($value);
        }
        
        if ($autosave) {
            $this->save();
        }
    }

    /**
     * Returns only those properties of this model that can be saved directly in the persistent
     * storage. It filters out all properties that have been added by the application.
     * 
     * @return array Array of filtered properties.
     * 
     * @todo
     */
    public function _getPropertiesForStorage() {
        return $this->_getProperties();
        /*
        $validProperties = array();
        $properties = $this->_getProperties();
        $modelInfo = static::_getModelInfo();
        
        $allowedProperties = array_keys($modelInfo['fields']);
        foreach($properties as $property => $value) {
            if (in_array($property, $allowedProperties)) {
                $validProperties[$property] = $value;
            }
        }
        
        return $validProperties;
        */
    }

    /*
     * PROPERTY AND METHOD OVERLOADING
     */
    /**
     * Will return the value of the id key for this model.
     * 
     * @return mixed
     */
    final public function _getId() {
        $getterMethod = static::_getAccessorName(static::_getIdProperty(), 'get');
        return $this->$getterMethod();
    }
    
    /**
     * Will set the value of the id key of this model to the given value.
     * 
     * @param mixed $value
     */
    final public function _setId($value) {
        $setterMethod = static::_getAccessorName(static::_getIdProperty(), 'set');
        $this->$setterMethod($value);
    }
    
    /**
     * Set a property to the value.
     * 
     * @param string $var Name of the property.
     * @param mixed $value Value to set to.
     */
    protected function _setProperty($var, $value) {
        /*
        // map to proper type
        $modelInfo = static::_getModelInfo();
        if (isset($modelInfo['fields'][$var])) {
            switch ($modelInfo['fields'][$var]['type']) {
                case 'int':
                    $value = intval($value);
                    break;

                case 'float':
                    $value = floatval($value);
                    break;
            }
        }
        */
        $this->_properties[$var] = $value;
    }
    
    /**
     * Return the wanted property.
     * 
     * @param string $var Name of the property.
     * @return mixed The property value.
     */
    public function _getProperty($var) {
        return @$this->_properties[$var];
    }

    /**
     * Checks whether the model has this property.
     * 
     * @param string $var Name of the property.
     * @return bool
     * 
     * @todo
     */
    /*
    final public function _hasProperty($var) {
        $modelInfo = static::_getModelInfo();
        return (isset($modelInfo['fields'][$var]));
    }
    */

    /**
     * Sets all properties to the given ones.
     * 
     * @param array $properties Array of properties to be set.
     */
    protected function _setProperties(array $properties) {
        foreach($properties as $var => $value) {
            $this->_setProperty($var, $value);
        }
    }
    
    /**
     * Returns all properties of the class.
     * 
     * @return array
     */
    public function _getProperties() {
        return $this->_properties;
    }

    /**
     * Set the given model property. It will try to call a defined setter first.
     * 
     * @param string $var Name of the property.
     * @param mixed $value Value to set to.
     */
    final public function __set($var, $value) {
        // try to call a defined setter if it exists
        $setterMethod = static::_getAccessorName($var, 'set');
        if (method_exists($this, $setterMethod)) {
            call_user_func(array($this, $setterMethod), $value);
            return;
        }
        
        $this->_setProperty($var, $value);
    }
    
    /**
     * Get a model property. It will try to call a defined getter first.
     * 
     * If the property does not exist then it will trigger an E_USER_NOTICE.
     * 
     * @param name $var Name of the property.
     * @return mixed
     */
    final public function __get($var) {
        // try to call a defined getter if it exists
        $getterMethod = static::_getAccessorName($var, 'get');
        if (method_exists($this, $getterMethod)) {
            return call_user_func(array($this, $getterMethod));
        }
        
        // if no getter then simply return the property if it exists
        if (array_key_exists($var, $this->_properties)) {
            return $this->_properties[$var];
        }
        
        // trigger a user notice if property not found
        $trace = debug_backtrace();
        trigger_error('Call to undefined model\'s property '. get_class($this) .'::'. $var .' in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_NOTICE);
        return null;
    }
    
    /**
     * Is the given model property set?
     * 
     * @param string $var Name of the property.
     * @return bool
     */
    final public function __isset($var) {
        return isset($this->_properties[$var]);
    }
    
    /**
     * Unset the given model property.
     * 
     * @param string $var Name of the property.
     */
    final public function __unset($var) {
        unset($this->_properties[$var]);
    }
    
    /**
     * Catch "false" setters and getters and do what they would normally do.
     * 
     * @param string $name Method name
     * @param array $arguments Array of arguments.
     * @return mixed The requested property for a getter, null for anything else.
     */
    final public function __call($name, $arguments) {
        $type = substr($name, 0, 3);
        
        // called a setter or a getter ?
        if (($type == 'set') OR ($type == 'get')) {
            $property = lcfirst(substr($name, 3));
            $methodName = '_'. $type .'Property';
            
            return $this->$methodName($property, @$arguments[0]);
        }
        
        // called an adder?
        /*
         * @todo
        if ($type == 'add') {
            $property = lcfirst(substr($name, 3));
            // adders can only be called on properties not defined in the core model
            $modelInfo = $this->_getModelInfo();
            if (isset($modelInfo['fields'][$property])) {
                $trace = debug_backtrace();
                trigger_error('Adders can only be called on properties not defined in the core model! Tried to call '. get_class($this) .'->'. $name .'() in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_ERROR);
                return null;
            }
            
            $var = $this->_getProperty($property);
            $var = (isset($var)) ? $var : array();
            $var[] = @$arguments[0];
            $this->_setProperty($property, $var);
            return null;
        }
        */
        
        // or maybe called a validator
        /*
         * @todo
        if (substr($name, 0, 8) == 'validate') {
            $property = lcfirst(substr($name, 8));
            
            return $this->_validateProperty($property, @$arguments[0]);
        }
        */
    
        // undefined method called!
        $trace = debug_backtrace();
        trigger_error('Call to undefined model\'s method '. get_class($this) .'::'. $name .'() in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_ERROR);
        return null;
    }
    
    /**
     * Catch "false" validators and do what they would normally do.
     * 
     * @param object $name
     * @param object $arguments
     * @return mixed 
     * 
     * @todo
     */
    /*
    final public static function __callStatic($name, $arguments) {
        // calling a validator?
        if (substr($name, 0, 8) == 'validate') {
            $property = lcfirst(substr($name, 8));
            
            return self::_validateProperty($property, $arguments[0]);
        }
        
        // undefined method called!
        $trace = debug_backtrace();
        trigger_error('Call to undefined model\'s method '. get_called_class() .'::'. $name .'() in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_ERROR);
        return null;
    }
    */

    /*
     * HELPERS
     */
    /**
     * Returns this model instance in the form of array. Useful for outputting models directly in AJAX response.
     * 
     * You should overwrite this method when you want to hide any properties of the model (don't return them in AJAX, like 'password' or delicate data).
     * 
     * @return array
     */
    public function toArray() {
        return $this->_getProperties();
    }

    /**
     * A debug method to use when dumping a model. The return value of this method would be passed to Debugger functions.
     * 
     * @return array
     */
    public function toDumpableArray() {
        return $this->_getProperties();
    }
    
}