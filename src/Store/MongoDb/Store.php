<?php
namespace Knit\Store\MongoDb;

use MongoClient;
use MongoConnectionException;
use MongoDB;
use MongoException;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use MD\Foundation\Debug\Timer;
use MD\Foundation\Utils\ArrayUtils;

use Knit\Criteria\CriteriaExpression;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Exceptions\StoreQueryErrorException;
use Knit\Store\MongoDb\CriteriaParser;
use Knit\Store\StoreInterface;
use Knit\Knit;

/**
 * MongoDB store implementation.
 *
 * @package    Knit
 * @subpackage Store\MongoDb
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Store implements StoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * MongoDB connected client.
     *
     * @var MongoClient
     */
    protected $client;

    /**
     * MongoDB database.
     *
     * @var MongoDB
     */
    protected $database;

    /**
     * MongoDB specific criteria parser.
     *
     * @var CriteriaParser
     */
    protected $criteriaParser;

    /**
     * Constructor.
     *
     * @param array                $config         Connection config.
     * @param CriteriaParser       $criteriaParser Knit criteria parser for MongoDB.
     * @param LoggerInterface|null $logger         [optional] Logger.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity,PHPMD.NPathComplexity)
     */
    public function __construct(array $config, CriteriaParser $criteriaParser, LoggerInterface $logger = null)
    {
        // check for all required info
        if (!ArrayUtils::checkValues($config, ['hostname', 'database'])) {
            throw new \InvalidArgumentException('Invalid config passed to '. __CLASS__ .'.');
        }

        $this->criteriaParser = $criteriaParser;
        $this->logger = $logger ? $logger : new NullLogger();

        try {
            // define MongoClient options
            $options = [
                'db' => $config['database'],
                'connect' => true
            ];

            if (ArrayUtils::checkValues($config, ['username', 'password'])) {
                $options['username'] = $config['username'];
                $options['password'] = $config['password'];
            }

            if (isset($config['replica_set'])) {
                $options['replicaSet'] = $config['replica_set'];
            }

            // define the DSN or connection definition string
            $dsn = 'mongodb://'
                . $config['hostname']
                . (isset($config['port']) && $config['port'] ? ':'. $config['port'] : '');

            // also add any additional hosts
            if (isset($config['hosts'])) {
                if (!is_array($config['hosts'])) {
                    throw new \InvalidArgumentException(__CLASS__ .' config option "hosts" must be an array of hosts.');
                }

                foreach ($config['hosts'] as $host) {
                    if (!ArrayUtils::checkValues($host, ['hostname'])) {
                        throw new \InvalidArgumentException(
                            __CLASS__ .' config option "hosts" must be an array of array hosts definitions'
                            .' with at least "hostname" key.'
                        );
                    }

                    $dsn .= ','. $host['hostname'] . (isset($host['port']) && $host['port'] ? ':'. $host['port'] : '');
                }
            }

            // finally connect and select the db
            $this->client = new MongoClient($dsn, $options);
            $this->database = $this->client->{$config['database']};

        } catch (MongoConnectionException $e) {
            throw new StoreConnectionFailedException($e->getMessage(), $e->getCode(), $e);
        }
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

        $criteria = $this->criteriaParser->parse($criteria);

        try {
            $cursor = $this->database->{$collection}->find($criteria);

            // apply params
            if (isset($params['start'])) {
                $cursor->skip(intval($params['start']));
            }

            if (isset($params['limit'])) {
                $cursor->limit(intval($params['limit']));
            }

            if (isset($params['orderBy'])) {
                $orderBy = $this->parseOrderBy($params);
                if (!empty($orderBy)) {
                    $cursor->sort($orderBy);
                }
            }

            // parse the results
            $result = [];
            while ($cursor->hasNext()) {
                $result[] = $cursor->getNext();
            }
        } catch (MongoException $e) {
            $this->log(
                'find',
                $collection,
                $criteria,
                ['params' => $params],
                'error'
            );
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log(
            'find',
            $collection,
            $criteria,
            [
                'params' => $params,
                'time' => $timer->stop(),
                'affected' => $cursor->count(true)
            ]
        );

        return $result;
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
        $timer = new Timer();

        $criteria = $this->criteriaParser->parse($criteria);

        if (isset($params['start'])) {
            $params['skip'] = $params['start'];
        }
        
        $params = array_merge(['limit' => 0, 'skip' => 0], $params);

        try {
            $result = $this->database->{$collection}->count($criteria, $params['limit'], $params['skip']);
        } catch (MongoException $e) {
            $this->log(
                'count',
                $collection,
                $criteria,
                ['params' => $params],
                'error'
            );
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log(
            'count',
            $collection,
            $criteria,
            ['time' => $timer->stop()]
        );

        return $result;
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

        // set the new ID
        $properties['_id'] = new MongoId();

        try {
            $result = $this->database->{$collection}->insert($properties);
        } catch (MongoException $e) {
            $this->log(
                'insert',
                $collection,
                $properties,
                [],
                'error'
            );
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log(
            'insert',
            $collection,
            $properties,
            ['time' => $timer->stop()]
        );

        if (!$result) {
            return null;
        }

        return isset($properties['_id']) ? (string) $properties['_id'] : null;
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

        $criteria = $this->criteriaParser->parse($criteria);

        // check if multiple update is possible
        $multiple = false;
        foreach (array_keys($criteria) as $key) {
            if ($key[0] === '$') {
                $multiple = true;
                break;
            }
        }

        try {
            $info = $this->database->{$collection}->update(
                $criteria,
                ['$set' => $properties], // use '$set' operator because update should only update those specific fields
                ['multiple' => $multiple]
            );
        } catch (MongoException $e) {
            $this->log(
                'update',
                $collection,
                $criteria,
                ['properties' => $properties],
                'error'
            );
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log(
            'update',
            $collection,
            $criteria,
            [
                'properties' => $properties,
                'time' => $timer->stop(),
                'affected' => is_array($info) ? $info['n'] : 'unknown'
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

        $criteria = $this->criteriaParser->parse($criteria);

        try {
            $info = $this->database->{$collection}->remove($criteria);
        } catch (MongoException $e) {
            $this->log('remove', $collection, $criteria, [], 'error');
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log(
            'remove',
            $collection,
            $criteria,
            [
                'time' => $timer->stop(),
                'affected' => is_array($info) ? $info['n'] : 'unknown'
            ]
        );
    }

    /**
     * Returns the MongoClient connection.
     *
     * @return MongoClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Returns the MongoDB database connection.
     *
     * @return MongoDB
     */
    public function getDatabase()
    {
        return $this->database;
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
        $message = sprintf('MongoDB Query: %s @ %s: %s', $type, $collection, json_encode($criteria));

        if (isset($criteria['params'])) {
            $message .= sprintf(' with params %s', json_encode($criteria['params']));
        }

        $this->logger->{$level}($message, $context);
    }

    /**
     * Parse `orderBy` and `orderDir` parameters.
     *
     * @param array  $params Query params.
     *
     * @return array
     */
    private function parseOrderBy(array $params)
    {
        $orderBy = [];

        if (is_array($params['orderBy'])) {
            foreach ($params['orderBy'] as $property => $orderDir) {
                if (is_string($orderDir)) {
                    $property = $orderDir;
                    $orderDir = Knit::ORDER_ASC;
                }

                $orderBy[$property] = $orderDir === Knit::ORDER_DESC ? -1 : 1;
            }
        } else {
            $orderBy[$params['orderBy']] = isset($params['orderDir'])
                && $params['orderDir'] === Knit::ORDER_DESC
                    ? -1
                    : 1;
        }

        return $orderBy;
    }
}
