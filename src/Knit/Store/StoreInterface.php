<?php
/**
 * Interface that must be implemented by any persistent store driver used with Knit ORM.
 * 
 * @package Knit
 * @subpackage Store
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Store;

use Psr\Log\LoggerInterface;

interface StoreInterface
{

    /**
     * Finds objects within a collection.
     * 
     * The $criteria array should conform to parsing of criteria (see elsewhere).
     * 
     * Should return array of results.
     * 
     * @param string $collection Name of the collection in which to look for.
     * @param array $criteria [optional] Criteria array.
     * @param array $params [optional] Other parameters, like orderBy or orderDir.
     * @return array
     */
    public function find($collection, array $criteria = array(), array $params = array());

    /**
     * Counts objects within a collection.
     * 
     * The $criteria array should conform to parsing of criteria (see elsewhere).
     * 
     * @param string $collection Name of the collection in which to look for.
     * @param array $criteria [optional] Criteria array.
     * @param array $params [optional] Other parameters, like orderBy or orderDir.
     * @return int|array Either an int or an array of ints if used groupBy param.
     */
    public function count($collection, array $criteria = array(), array $params = array());

    /**
     * Creates an object with the given properties in the persistent store.
     * 
     * @param string $collection Name of the collection in which to create new object.
     * @param array $properties Properties of the new object.
     * @return int|string ID of the created object.
     */
    public function add($collection, array $properties);

    /**
     * Updates an object matching the $criteria with the given $properties.
     * 
     * @param string $collection Name of the collection in which to update.
     * @param array $criteria [optional] Criteria array.
     * @param array $properties Properties of the new object.
     */
    public function update($collection, array $criteria, array $properties);

    /**
     * Deletes objects with the given $criteria from the collection.
     * 
     * @param string $collection Name of the collection from which to delete objects.
     * @param array $criteria Criteria on which to delete objects.
     */
    public function delete($collection, array $criteria);

    /**
     * Should return information about the collection structure.
     * 
     * The array returned should conform to the structure array definition.
     * 
     * @param string $collection Name of the collection.
     * @return array
     */
    public function structure($collection);

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Sets the store logger.
     * 
     * @param LoggerInterface $logger Must implement PSR-3 LoggerInterface.
     */
    public function setLogger(LoggerInterface $logger);

}