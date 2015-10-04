<?php
namespace Knit\Store;

use Knit\Criteria\CriteriaExpression;

/**
 * Persistent store driver interface.
 *
 * @package    Knit
 * @subpackage Store
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
interface StoreInterface
{
    /**
     * Finds items within a collection.
     *
     * @param string $collection Name of the collection in which to look for.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $params [optional] Other parameters, like `orderBy` or `orderDir`.
     *
     * @return array
     */
    public function find($collection, CriteriaExpression $criteria = null, array $params = array());

    /**
     * Counts items within a collection.
     *
     * MUST return either an integer or an array of integers if used `groupBy` param.
     *
     * @param string $collection Name of the collection in which to look for.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $params [optional] Other parameters, like `orderBy` or `orderDir`.
     *
     * @return integer|array
     */
    public function count($collection, CriteriaExpression $criteria = null, array $params = array());

    /**
     * Persists a new item in the collection.
     *
     * MUST return an identifier of the created object.
     *
     * @param string $collection Name of the collection in which to create new object.
     * @param array $properties Properties of the new object.
     *
     * @return integer|string
     */
    public function add($collection, array $properties);

    /**
     * Updates items in the collection based on the given criteria.
     *
     * @param string $collection Name of the collection in which to update.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $properties Properties to be updated.
     */
    public function update($collection, CriteriaExpression $criteria = null, array $properties = []);

    /**
     * Removes items from the collection based on the given criteria.
     *
     * @param string $collection Name of the collection from which to remove items.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     */
    public function remove($collection, CriteriaExpression $criteria = null);
}
