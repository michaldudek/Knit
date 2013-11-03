<?php
/**
 * Abstract entity class.
 * 
 * @package Knit
 * @subpackage Entity
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Entity;

use RuntimeException;

use MD\Foundation\Debug\Interfaces\Dumpable;
use MD\Foundation\Utils\ObjectUtils;
use MD\Foundation\Utils\StringUtils;

use Knit\Entity\Repository;
use Knit\Exceptions\StructureDefinedException;
use Knit\KnitOptions;
use Knit\Knit;

abstract class AbstractEntity implements Dumpable
{

    /**
     * Reference to this entity's repository.
     * 
     * @var Repository
     */
    protected $_repository;

    /**
     * The entity's properties.
     * 
     * @var array
     */
    protected $_properties = array();

    /**
     * Should notices about this entity being unlinked to a repository be triggered?
     * 
     * Not linking an entity to a repository can lead to some unexpected behavior and turns off several features
     * like validation and casting types.
     * 
     * @var bool
     */
    protected $_triggerUnlinkedNotices = true;

    /*****************************************************
     * MAPPING
     *****************************************************/
    /**
     * Implement this function if you want to keep the entity structure in the code instead of the database.
     * 
     * It should return a structure array.
     * 
     * @return array
     */
    public static function _getStructure() {
        return array();
    }

    /**
     * Updates the entity with data sent in array.
     * 
     * The data is validated before setting it.
     * 
     * @param array $data Updated entity properties.
     */
    public function updateWithData(array $data) {
        $repository = $this->_getRepository();

        // prevent updating the main key
        if ($repository) {
            $idProperty = $repository->getIdProperty();
            if (isset($data[$idProperty])) {
                unset($data[$idProperty]);
            }
        }

        $valid = $repository ? $repository->validateData($data) : true;

        if ($valid) {    
            foreach($data as $var => $value) {
                call_user_func_array(array($this, ObjectUtils::setter($var)), array($value));
            }
        }
    }

    /*****************************************************
     * PROPERTY AND METHOD OVERLOADING
     *****************************************************/
    /**
     * Will return the value of the id property for this entity.
     * 
     * @return mixed
     */
    public function _getId() {
        return $this->_getProperty($this->_getIdProperty());
    }
    
    /**
     * Will set the value of the id property of this model to the given value.
     * 
     * @param mixed $value
     */
    public function _setId($value) {
        $this->_setProperty($this->_getIdProperty(), $value);
    }

    /**
     * Returns the ID property name.
     * 
     * @return string
     */
    public function _getIdProperty() {
        $repository = $this->_getRepository();
        return $repository ? $repository->getIdProperty() : 'id';
    }

    /**
     * Sets all properties to the given ones.
     * 
     * Setting properties through this method will not use the setters nor validators
     * but rather set the values directly.
     * 
     * @param array $properties Array of properties to be set.
     */
    public function _setProperties(array $properties) {
        foreach($properties as $var => $value) {
            $this->_setProperty($var, $value);
        }
    }
    
    /**
     * Returns all properties of the entity
     * 
     * Getting properties through this method will not use the getters,
     * but rather get the values directly.
     * 
     * @return array
     */
    public function _getProperties() {
        return $this->_properties;
    }

    /**
     * Set a property.
     * 
     * Setting property through this method will not use the setter nor validators
     * but rather set the value directly.
     * 
     * @param string $property Name of the property.
     * @param mixed $value Value to set to.
     */
    public function _setProperty($property, $value) {
        $this->_properties[$property] = $this->_castPropertyType($property, $value);
    }
    
    /**
     * Return the wanted property.
     * 
     * Getting property through this method will not use the getter,
     * but rather get the value directly.
     * 
     * @param string $var Name of the property.
     * @return mixed The property value. If no such property then null will be returned.
     */
    public function _getProperty($var) {
        return isset($this->_properties[$var]) ? $this->_properties[$var] : null;
    }

    /**
     * Checks whether the entity has this property.
     * 
     * @param string $var Name of the property.
     * @return bool
     */
    final public function _hasProperty($var) {
        $repository = $this->_getRepository();

        if ($repository) {
            $structure = $repository->getEntityStructure();
            return isset($structure[$var]);
        }

        return true;
    }

    /**
     * Validates the given value for the given property.
     * 
     * @param string $var Name of the property.
     * @param mixed $value Whatever value to be set.
     * @return bool
     */
    public function _validateProperty($var, $value) {
        $repository = $this->_getRepository();
        return $repository ? $repository->validateProperty($var, $value) : true;
    }
   
   /**
     * Set the property. Before property is set it is validated.
     * 
     * It will try to call a defined setter first.
     * 
     * @param string $var Name of the property.
     * @param mixed $value Value to set to.
     */
    final public function __set($var, $value) {
        // try to call a defined setter if it exists
        $setter = ObjectUtils::setter($var);
        if (method_exists($this, $setter)) {
            call_user_func(array($this, $setter), $value);
            return;
        }

        if ($this->_validateProperty($var, $value)) {
            $this->_setProperty($var, $value);
        }
    }
    
    /**
     * Get the property. It will try to call a defined getter first.
     * 
     * If the property does not exist then it will trigger an E_USER_NOTICE.
     * 
     * @param name $var Name of the property.
     * @return mixed
     */
    final public function __get($var) {
        // try to call a defined getter if it exists
        $getter = ObjectUtils::getter($var);
        if (method_exists($this, $getter)) {
            return call_user_func(array($this, $getter));
        }
        
        // if no getter then simply return the property if it exists
        if (array_key_exists($var, $this->_properties)) {
            return $this->_properties[$var];
        }
        
        // trigger a user notice if property not found
        $trace = debug_backtrace();
        trigger_error('Call to undefined entity\'s property "'. $this->__getClass() .'::$'. $var .'" in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_NOTICE);
        return null;
    }
    
    /**
     * Is the given property set?
     * 
     * @param string $var Name of the property.
     * @return bool
     */
    final public function __isset($var) {
        return isset($this->_properties[$var]);
    }
    
    /**
     * Unset the given property.
     * 
     * @param string $var Name of the property.
     */
    final public function __unset($var) {
        unset($this->_properties[$var]);
    }
    
    /**
     * Overload setters and getters and do what they would normally do.
     * 
     * @param string $name Method name
     * @param array $arguments Array of arguments.
     * @return mixed The requested property value for a getter, null for anything else.
     */
    final public function __call($methodName, $arguments) {
        $type = strtolower(substr($methodName, 0, 3));
        
        // called a setter or a getter ?
        if (($type == 'set') || ($type == 'get')) {
            $property = lcfirst(substr($methodName, 3));

            // decide on property name by checking if a camelCase exists first
            // and if not trying the under_scored
            $property = ($this->_hasProperty($property)) ? $property : StringUtils::toSeparated($property, '_');

            if ($type == 'set') {
                $value = isset($arguments[0]) ? $arguments[0] : null;

                // validate before setting
                if ($this->_validateProperty($property, $value)) {
                    $this->_setProperty($property, $value);
                }

                return;
            } else if ($type == 'get') {
                return $this->_getProperty($property);
            }
        }

        // called an isser?
        if (strtolower(substr($methodName, 0, 2)) === 'is') {
            $property = lcfirst(substr($methodName, 2));
            $property = ($this->_hasProperty($property)) ? $property : StringUtils::toSeparated($property, '_');

            $value = $this->_getProperty($property);
            // cast '0' as false
            return (!$value || $value == '0') ? false : true;
        }
        
        // called an adder?
        /*
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
        if (substr($methodName, 0, 8) == 'validate') {
            $property = lcfirst(substr($methodName, 8));

            // decide on property name by checking if a camelCase exists first
            // and if not trying the under_scored
            $property = ($this->_hasProperty($property)) ? $property : StringUtils::toSeparated($property, '_');

            // require at least one argument
            if (!isset($arguments[0])) {
                $trace = debug_backtrace();
                $file = isset($trace[0]['file']) ? $trace[0]['file'] : 'unknown';
                $line = isset($trace[0]['line']) ? $trace[0]['line'] : 'unknown';
                trigger_error('Function "'. $this->__getClass() .'::'. $methodName .'()" requires one argument, none given in '. $file .' on line '. $line, E_USER_ERROR);
            }
            
            return $this->_validateProperty($property, $arguments[0]);
        }
    
        // undefined method called!
        $trace = debug_backtrace();
        trigger_error('Call to undefined method '. $this->__getClass() .'::'. $methodName .'() in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_ERROR);
        return null;
    }
   
    /*****************************************************
     * ACTIVE RECORD
     *****************************************************/
    // functions that cause Entities to behave like Active Record, ie. allow to persist themselves
    
    /**
     * Saves the entity in its persistent store.
     */
    public function save() {
        $repository = $this->_getRepository();

        if (!$repository) {
            throw new RuntimeException('Trying to call "'. get_class($this) .'->save()" method on an entity object without linked repository! Please link a repository by calling "_setRepository()" first.');
        }

        $repository->save($this);
    }

    /**
     * Removes the entity from its persistent store.
     */
    public function delete() {
        $repository = $this->_getRepository();

        if (!$repository) {
            throw new RuntimeException('Trying to call "'. get_class($this) .'->delete()" method on an entity object without linked repository! Please link a repository by calling "_setRepository()" first.');
        }

        $repository->delete($this);
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Sets reference to this entity's repository.
     * 
     * @param Repository $repository
     * 
     * @throws RepositoryDefinedException When trying to redeclare repository for this entity.
     */
    public function _setRepository(Repository $repository) {
        if ($this->_repository) {
            throw new RepositoryDefinedException('Cannot redeclare repository for entity "'. get_class($this) .'".');
        }

        $this->_repository = $repository;
    }

    /**
     * Returns reference to this entity's repository.
     * 
     * @return Repository
     */
    public function _getRepository() {
        if (!$this->_repository && $this->_triggerUnlinkedNotices) {
            trigger_error('Entity "'. get_class($this) .'" has not been linked to a repository which can lead to unexpected behavior!', E_USER_NOTICE);
        }

        return $this->_repository;
    }

    /**
     * Should notices about this entity being unlinked to a repository be triggered?
     * 
     * Not linking an entity to a repository can lead to some unexpected behavior and turns off several features
     * like validation and casting types.
     * 
     * However, it is useful in tests.
     * 
     * @param bool $trigger [optional] Should the noticed be triggered?
     */
    public function _setUnlinkedNotices($trigger = true) {
        $this->_triggerUnlinkedNotices = $trigger;
    }

    /**
     * Magical method that allows to cast the entity to string.
     * 
     * Checks if there exists "name" or "title" field and uses them as a result.
     * 
     * @return string
     * 
     * @throws RuntimeException When cannot magically resolve a string value.
     */
    public function __toString() {
        $name = $this->getName();
        if (is_string($name) && !empty($name)) {
            return $name;
        }

        $title = $this->getTitle();
        if (is_string($title) && !empty($title)) {
            return $title;
        }

        throw new RuntimeException('Entity "'. get_class($this) .'" does not implement __toString() method and cannot be cast to string.');
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Returns this model instance in the form of array. Useful for outputting models directly in AJAX response.
     * 
     * You should overwrite this method when you want to hide any properties of the model (don't return them in AJAX, like 'password' or delicate data).
     * 
     * @return array
     */
    public function toArray() {
        $repository = $this->_getRepository();

        $properties = $this->_getProperties();
        $hiddenPropertyNames = $repository ? $repository->getHiddenPropertyNames() : array();

        foreach($properties as $property => $value) {
            if (in_array($property, $hiddenPropertyNames)) {
                unset($properties[$property]);
            }
        }

        return $properties;
    }

    /**
     * A debug method to use when dumping a model. The return value of this method would be passed to Debugger functions.
     * 
     * @return array
     */
    public function toDumpableArray() {
        return $this->_getProperties();
    }

    /**
     * Returns full name of this entity class.
     * 
     * @return string
     */
    public function __getClass() {
        return get_class($this);
    }

    /**
     * Returns full name of this entity class.
     * 
     * @return string
     */
    public static function __class() {
        return get_called_class();
    }

    /**
     * Casts proper PHP type on the property's value.
     * 
     * @param string $property Name of the property.
     * @param mixed $value Value for the property to be casted.
     * @return mixed
     */
    public function _castPropertyType($property, $value) {
        $repository = $this->_getRepository();
        if (!$repository) {
            return $value;
        }

        $structure = $repository->getEntityStructure();

        if (isset($structure[$property])) {
            switch($structure[$property]['type']) {
                case KnitOptions::TYPE_INT:
                    // cast booleans manually to ensure proper results
                    if ($value === false || $value === true) {
                        $value = $value ? 1 : 0;
                        break;
                    }

                    $value = intval($value);
                    break;

                case KnitOptions::TYPE_FLOAT:
                    $value = floatval($value);
                    break;

                case KnitOptions::TYPE_ENUM:
                    // cast booleans manually to ensure proper results (e.g. false casts to empty string) - numbers
                    if ($value === false || $value === true) {
                        $value = $value ? '1' : '0';
                        break;
                    }

                    $value = strval($value);
                    break;

                default:
                    // by default all properties are strings
                    $value = strval($value);
            }
        }

        return $value;
    }

}