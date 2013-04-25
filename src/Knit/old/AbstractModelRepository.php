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

use MD\Foundation\Utils\ObjectUtils;

use Knit\Store\StoreInterface;
use Knit\Exceptions\NoStoreException;

abstract class AbstractModelRepository
{

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
            
            $from = call_user_func_array(array($from, 'find'), array(array_merge(array(
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
            $from = call_user_func_array(array($from, 'find'), array(array_merge(array(
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

}