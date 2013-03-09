<?php
/**
 * Abstract Model Repository.
 * 
 * Contains factory/repository methods for Knit.
 * 
 * @package Knit
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit;

use Splot\Foundation\Utils\ObjectUtils;

use Knit\Store\StoreInterface;
use Knit\Exceptions\NoStoreException;

abstract class AbstractModelRepository
{

    /**
     * Collection name where objects of this model are stored in the persistent store.
     * 
     * @var string
     */
    protected static $_collection;

    /**
     * Some information about the collection.
     * 
     * @todo
     * @var array
     */
    protected static $_collectionInfo = array();

    /**
     * Name of the ID / Primary Key property for this model class.
     * 
     * @var string
     */
    protected static $_idProperty = 'id';

    /**
     * Persistent store where data is saved.
     * 
     * @var StoreInterface
     */
    protected static $_store;

    /*
     FACTORY METHODS
     */
    /**
     * Return an array of objects based on the given criteria and parameters.
     * 
     * @param array $criteria [optional] Array of criteria.
     * @param array $params [optional] Array of optional parameters (like start, limit, etc).
     * @return array Array of requested objects.
     */
    public static function get(array $criteria = array(), array $params = array()) {
        $store = static::_getStore();
        $objects = array();
        
        $result = $store->get(static::$_collection, $criteria, $params);
        
        foreach($result as $item) {
            $objects[] = static::_instantiateWithData($item);
        }
        
        return $objects;
    }
    
    /**
     * Will get one instance of this data model based on passed criteria.
     * 
     * @param array $criteria [optional] Array of criteria.
     * @param array $params [optional] Array of optional parameters (like start, limit, etc).
     * @return object|null
     */
    public static function getOne(array $criteria = array(), array $params = array()) {
        $store = self::_getStore();
        
        $result = $store->get(static::$_collection, $criteria, array_merge($params, array(
            'limit' => 1
        )));
        
        if (empty($result)) {
            return null;
        }
        
        return static::_instantiateWithData($result);
    }
    
    /**
     * Will get one instance of this data model found by the given primary (main) key.
     * 
     * @param mixed $id Value of the ID.
     * @return object|null
     */
    public static function getById($id) {
        $idProperty = static::$_idProperty;

        return static::getOne(array(
            $idProperty => $id
        ));
    }
    
    /**
     * Provides an instance of this data model based on the given data.
     * 
     * If none found in database already then it will create it using this data.
     * 
     * @param array $data Array of criteria/data.
     * @return object
     */
    public static function provide(array $data) {
        $object = static::getOne($data);
        if (!$object) {
            $object = static::createWithData($data);
            $object->save();
        }

        return $object;
    }

    /**
     * Counts how many persisted objects of this model match the given criteria (if any).
     * 
     * @param array $criteria [optional] Array of optional criteria.
     * @param array $params [optional] Array of optional params.
     * @return mixed
     */
    public static function count(array $criteria = array(), array $params = array()) {
        $store = static::_getStore();
        return $store->count(static::$_collection, $criteria, $params);
    }

    /**
     * Will remove any instances of this model that match the given criteria from the persistent storage.
     * 
     * @param array $criteria Criteria on which to delete objects. Same as criteria passed to any other factory methods.
     */
    public static function multiDelete(array $criteria) {
        $store = static::_getStore();
        $store->delete(static::$_collection, $criteria);
    }

    /**
     * Attempts to perform a 1:1 join between two sets of models on given criteria.
     * 
     * @param array|object $objects A collection of objects that we will be joining to (one instance of Model is acceptable too).
     * @param array|string $from Either a collection of objects that we will be joining from or a name (string) of the model that we're gonna be using. Joining from prepopulated array TBD later. @todo
     * @param string $onField Name of the field that's in $objects objects that we're gonna join on.
     * @param string $intoField [optional] Name of a new field to create for the joined objects. Defaults to the same as $onField.
     * @param string $fromKey [optional] Name of the field that's in $from that we will be joining on. Defaults to the main key of that model.
     * @param array $additionalCriteria [optional] An array of additional criteria to select $from objects on (same as used for Splot Model::get() method).
     * @param bool $filterEmpty [optional] Should those objects from $objects that didn't end up with a field assigned be filtered out? Default true.
     * @return array A collection of objects.
     * 
     * @todo Remove the code for MDModel
     */
    public static function join($objects, $from, $onField, $intoField = null, $fromKey = null, $additionalCriteria = array(), $filterEmpty = true) {
        $objects = (is_array($objects)) ? $objects : array($objects);
        $intoField = (isset($intoField)) ? $intoField : $onField;

        // reset keys just in case
        $objects = ObjectUtils::resetKeys($objects);
        
        // filter out field values that we're gonna be joining on
        $onFieldValues = ObjectUtils::keyFilter($objects, $onField);
        
        // get the objects we're interested in or filter them out from $from array
        if (is_string($from)) {
            // figure out what key to use there if none given
            $fromKey = (!isset($fromKey)) ? call_user_func(array($from, '_getIdProperty')) : $fromKey;
            
            $from = call_user_func_array(array($from, 'get'), array(array_merge(array(
                $fromKey => $onFieldValues
            ), $additionalCriteria)));
        } else {
            // an array of objects has been given so that means we're suppose to only filter from them
            $from = (is_array($from)) ? $from : array($from);
            if (empty($from)) return $objects;
            
            /*
            // figure out what key to use there if none given
            if (!is_a($from[0], 'MDModel')) {
                trigger_error('Not an array of MDModels given for MDModel::join() method.', E_USER_ERROR);
                return $objects;
            }
            $fromKey = (!isset($fromKey)) ? $from[0]->_getMainKeyName() : $fromKey;
            */
            
            // TBD later
        }
        
        // these $from values match the criteria, just need to assign them nicely
        $from = ObjectUtils::keyExplode($from, $fromKey);
        
        foreach($objects as $object) {
            if (isset($from[$object->$onField])) {
                $object->_setProperty($intoField, $from[$object->$onField]);
            }
        }
        
        // filter out those $objects that don't have a new field assigned
        if ($filterEmpty) {
            foreach($objects as $i => $object) {
                if (!isset($object->$intoField)) {
                    unset($objects[$i]);
                }
            }
        }
        
        return $objects;
    }
    
    /**
     * Attempts to perform a 1:many join on the given objects based on the given criteria.
     * 
     * @param array|objects $objects A collection of objects that we will be joining to (one instance of Model is acceptable too).
     * @param array|string $from Either a collection of objects that we will be joining from or a name (string) of the model that we're gonna be using.
     * @param string $onField Name of the field that's in $from objects that we're gonna join on.
     * @param string $intoField Name of a new field to create for the joined objects. This field cannot be defined in the core model!
     * @param array $additionalCriteria [optional] An array of additional criteria to select $from objects on (same as used for Model::get() method).
     * @param array $additionalParams [optional] An array of additional params to select $from objects (same as used for Model::get() method).
     * @return array A collection of objects
     * 
     * @todo
     */
    public static function joinMany($objects, $from, $onField, $intoField, $additionalCriteria = array(), $additionalParams = array()) {
        $objects = (is_array($objects)) ? $objects : array($objects);
        if (empty($objects)) return $objects;
        
        // reset keys just in case
        $objects = ObjectUtils::resetKeys($objects);
        
        $objectsClass = Debugger::getClass($objects[0]);
        $objectsMainKey = call_user_func(array($objectsClass, '_getIdProperty'));
        $objectIds = ObjectUtils::keyFilter($objects, $objectsMainKey);
        
        // get the objects we're interested in or filter them out from $from array
        if (is_string($from)) {
            $from = call_user_func_array(array($from, 'get'), array(array_merge(array(
                $onField => $objectIds
            ), $additionalCriteria), $additionalParams));
        } else {
            // an array of objects has been given so that means we're suppose to only filter from them
            $from = (is_array($from)) ? $from : array($from);
            if (empty($from)) return $objects;
        }
        
        $objects = ObjectUtils::keyExplode($objects, $objectsMainKey);
        
        $adderMethod = 'add'. ucfirst($intoField);
        foreach($from as $object) {
            if (isset($objects[$object->$onField])) {
                $objects[$object->$onField]->$adderMethod($object);
            }
        }
        
        // reset keys
        $objects = ObjectUtils::resetKeys($objects);
        return $objects;
    }

    /*
     * MAPPING
     */
    /**
     * Instantiate this class with the given parameters. Usually called straight after getting results from the database.
     * 
     * @param array $data [optional] Properties of this model.
     * @return object Instance of this model.
     */
    public static function _instantiateWithData(array $data = array()) {
        $object = new static();
        $object->willCreateObject();
        $object->_setProperties($data);
        $object->didCreateObject();
        return $object;
    }

    /**
     * Will create an instance of this data model and fill its properties with the passed data.
     * 
     * Before passing the data it will call willCreateObject() method. You should set any default properties in that method.
     * After passing all the data (and calling all setters) it will call didCreateObject(). You should perform any additional setting up in that method. 
     * 
     * It try to use all the defined setters.
     * 
     * @param array $data Array of properties for the model to have.
     * @return object
     */
    public static function createWithData($data) {
        $object = new static();
        
        $object->willCreateObject();
        
        foreach($data as $var => $value) {
            $methodName = static::_getAccessorName($var, 'set');
            $object->$methodName($value);
        }
        
        $object->didCreateObject();
        return $object;
    }

    /*
     * PROPERTY AND METHOD OVERLOADING
     */
    /**
     * Will return the name of the id key for this model.
     * 
     * @return string
     */
    final public static function _getIdProperty() {
        return static::$_idProperty;
    }

    /*
     * HELPERS
     */
    /**
     * Creates an accessor function name for the given variable name, ie. setter or getter.
     * 
     * @param string $name Name of the variable.
     * @param string $type [optional] Type of the accessor, 'get'/'set'/'is'. Default: 'get'.
     */
    public static function _getAccessorName($name, $type = 'get') {
        return strtolower($type) . ucfirst(StringUtils::toCamelCase($name, '_'));
    }

    /*
     * SETTERS AND GETTERS
     */
    /**
     * Sets the store that is responsible for persisting this model class.
     * 
     * @param StoreInterface $store
     */
    public static function _setStore(StoreInterface $store) {
        static::$_store = $store;
    }

    /**
     * Returns the store that is responsible for persisting this model class.
     * 
     * @return StoreInterface
     * 
     * @throws NoStoreException When there has been no store defined for this model.
     */
    public static function _getStore() {
        if (!static::$_store) {
            throw new NoStoreException('No store defined for model "'. get_called_class() .'".');
        }

        return static::$_store;
    }

}