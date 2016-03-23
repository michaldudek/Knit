<?php
namespace Knit\Store\Memory;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use MD\Foundation\Debug\Timer;
use MD\Foundation\Utils\ArrayUtils;

use Knit\Criteria\CriteriaExpression;
use Knit\Store\Memory\CriteriaMatcher;
use Knit\Store\StoreInterface;
use Knit\Knit;

/**
 * Memory store implementation. Mainly used for testing etc.
 *
 * @package    Knit
 * @subpackage Store\Memory
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2016 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class Store implements StoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Criteria matcher.
     *
     * @var CriteriaMatcher
     */
    protected $matcher;

    /**
     * Collections.
     *
     * @var array
     */
    protected $collections = [];

    /**
     * Internal ID.
     *
     * @var integer
     */
    protected $currentId = 1;

    /**
     * Constructor.
     *
     * @param CriteriaMatcher $matcher Criteria matcher.
     * @param LoggerInterface $logger  [optional] Logger.
     */
    public function __construct(CriteriaMatcher $matcher, LoggerInterface $logger = null)
    {
        $this->matcher = $matcher;
        $this->logger = $logger ? $logger : new NullLogger();
    }

    /**
     * Finds items within a collection.
     *
     * @param string $collection Name of the collection in which to look for.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $params [optional] Other parameters, like `orderBy` or `orderDir`.
     *
     * @return array
     */
    public function find($collection, CriteriaExpression $criteria = null, array $params = [])
    {
        $timer = new Timer();

        $this->ensureCollection($collection);

        // filter the collection items
        $items = [];
        foreach ($this->collections[$collection] as $item) {
            if ($this->matcher->matches($item, $criteria)) {
                $items[] = $item;
            }
        }

        // apply params
        if (isset($params['orderBy'])) {
            $items = $this->sort($items, $params);
        }

        if (isset($params['start'])) {
            $items = array_slice($items, intval($params['start']));
        }

        if (isset($params['limit'])) {
            $items = array_slice($items, 0, intval($params['limit']));
        }

        // log this query
        $this->log(
            'find',
            $collection,
            $criteria ? $criteria->getRaw() : [],
            [
                'params' => $params,
                'time' => $timer->stop(),
                'affected' => count($items)
            ]
        );

        return $items;
    }

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
    public function count($collection, CriteriaExpression $criteria = null, array $params = [])
    {
        $items = $this->find($collection, $criteria, $params);
        return count($items);
    }

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
    public function add($collection, array $properties)
    {
        $timer = new Timer();

        $this->ensureCollection($collection);

        $properties['id'] = $this->currentId++;

        $this->collections[$collection][] = $properties;

        // log this query
        $this->log(
            'add',
            $collection,
            [],
            [
                'properties' => $properties,
                'time' => $timer->stop(),
                'affected' => 1
            ]
        );

        return $properties['id'];
    }

    /**
     * Updates items in the collection based on the given criteria.
     *
     * @param string $collection Name of the collection in which to update.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $properties Properties to be updated.
     */
    public function update($collection, CriteriaExpression $criteria = null, array $properties = [])
    {
        $timer = new Timer();

        $this->ensureCollection($collection);

        // update the matching items
        $affected = 0;
        foreach ($this->collections[$collection] as &$item) {
            if ($this->matcher->matches($item, $criteria)) {
                $item = array_merge($item, $properties);
                $affected++;
            }
        }

        // log this query
        $this->log(
            'upate',
            $collection,
            $criteria ? $criteria->getRaw() : [],
            [
                'properties' => $properties,
                'time' => $timer->stop(),
                'affected' => $affected
            ]
        );
    }

    /**
     * Removes items from the collection based on the given criteria.
     *
     * @param string $collection Name of the collection from which to remove items.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     */
    public function remove($collection, CriteriaExpression $criteria = null)
    {
        $timer = new Timer();

        $this->ensureCollection($collection);

        // remove the matching items
        $affected = 0;
        foreach ($this->collections[$collection] as $i => $item) {
            if ($this->matcher->matches($item, $criteria)) {
                unset($this->collections[$collection][$i]);
                $affected++;
            }
        }

        // log this query
        $this->log(
            'remove',
            $collection,
            $criteria ? $criteria->getRaw() : [],
            [
                'time' => $timer->stop(),
                'affected' => $affected
            ]
        );
    }

    /**
     * Makes sure that a collection exists.
     *
     * @param string $name       Collection name.
     */
    protected function ensureCollection($name)
    {
        if (!isset($this->collections[$name])) {
            $this->collections[$name] = [];
        }
    }

    /**
     * Logs a query to the logger.
     *
     * @param string $type       Query type, e.g. `find` or `remove`.
     * @param string $collection Collection name on which the query was executed.
     * @param array  $criteria   Query criteria.
     * @param array  $context    Query context.
     * @param string $level      [optional] Log level. Default `debug`.
     */
    protected function log($type, $collection, array $criteria, array $context, $level = 'debug')
    {
        $message = sprintf('Memory Query: %s @ %s: %s', $type, $collection, json_encode($criteria));

        if (isset($context['params'])) {
            $message .= sprintf(' with params %s', json_encode($context['params']));
        }

        $context['criteria'] = $criteria;

        $this->logger->{$level}($message, $context);
    }

    /**
     * Sort the items based on `orderBy` and `orderDir` parameters.
     *
     * @param array $items  Items to be sorted.
     * @param array $params Query params.
     *
     * @return array
     */
    protected function sort(array $items, array $params)
    {
        if (is_array($params['orderBy'])) {
            foreach ($params['orderBy'] as $property => $orderDir) {
                if (is_string($orderDir)) {
                    $property = $orderDir;
                    $orderDir = Knit::ORDER_ASC;
                }

                $items = ArrayUtils::multiSort($items, $property, $orderDir === Knit::ORDER_DESC);

                // memory sorting only supports one level sorting
                break;
            }

            return $items;
        }

        $orderDir = isset($params['orderDir']) ? $params['orderDir'] : Knit::ORDER_ASC;

        return ArrayUtils::multiSort($items, $params['orderBy'], $orderDir === Knit::ORDER_DESC);
    }
}
