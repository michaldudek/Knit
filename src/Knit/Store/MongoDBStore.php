<?php
/**
 * MongoDB store driver.
 * 
 * @package Knit
 * @subpackage Store
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Store;

use InvalidArgumentException;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Debug\Timer;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Utils\StringUtils;

use MD\Foundation\Exceptions\NotImplementedException;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\FieldValue;
use Knit\Entity\Repository;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Exceptions\StoreQueryErrorException;
use Knit\Store\StoreInterface;
use Knit\KnitOptions;
use Knit\Knit;

use MongoClient;
use MongoDB;
use MongoId;
use MongoConnectionException;

class MongoDBStore implements StoreInterface
{

    /**
     * The store logger.
     * 
     * @var LoggerInterface
     */
    protected $logger;

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
    protected $db;

    /**
     * Constructor.
     * 
     * @param array $config Array of all information required to connect to the store (e.g. host, user, pass, database name, port, etc.)
     */
    public function __construct(array $config) {
        // check for all required info
        if (!ArrayUtils::checkValues($config, array('hostname', 'database'))) {
            throw new InvalidArgumentException('"'. get_called_class() .'::__construct()"  expects 1st argument to be an array containing non-empty key "hostname", "'. implode('", "', array_keys($config)) .'" given.');
        }

        $this->logger = new NullLogger();

        try  {
            // define MongoClient options
            $options = array(
                'db' => $config['database'],
                'connect' => true
            );

            if (ArrayUtils::checkValues($config, array('username', 'password'))) {
                $options['username'] = $config['username'];
                $options['password'] = $config['password'];
            }

            if (isset($config['replica_set'])) {
                $options['replicaSet'] = $config['replica_set'];
            }

            // define the DSN or connection definition string
            $dsn = 'mongodb://'. $config['hostname'] . (isset($config['port']) ? ':'. $config['port'] : '');

            // also add any additional hosts
            if (isset($config['hosts'])) {
                if (!is_array($config['hosts'])) {
                    throw new InvalidArgumentException('MongoDBStore config option "hosts" must be an array of hosts, "'. Debugger::getType($options['hosts']) .'" given.');
                }

                foreach($config['hosts'] as $host) {
                    if (!ArrayUtils::checkValues($host, array('hostname'))) {
                        throw new InvalidArgumentException('MongoDBStore config option "hosts" must be an array of array hosts definitions with at least "hostname" key.');
                    }

                    $dsn .= ','. $host['hostname'] . (isset($host['port']) ? ':'. $host['port'] : '');
                }
            }

            // finally connect and select the db
            $this->client = new MongoClient($dsn, $options);
            $this->db = $this->client->{$config['database']};

        } catch(MongoConnectionException $e) {
            throw new StoreConnectionFailedException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /*****************************************************
     * STORE INTERFACE IMPLEMENTATION
     *****************************************************/
    /**
     * Customizes repository for which it was set as a store by forcing entity's ID property to be "_id".
     * 
     * @param Repository $repository Repository to which this store has been bound.
     */
    public function didBindToRepository(Repository $repository) {
    }

    /**
     * Performs a SELECT query on the given collection.
     * 
     * @param string $collection Name of the collection to select from.
     * @param CriteriaExpression $criteria Criteria on which to select.
     * @param array $params [optional] Paramaters of the select (like order, start, limit, groupby, etc.)
     * @return array
     */
    public function find($collection, CriteriaExpression $criteria = null, array $params = array()) {
        $criteria = $this->parseCriteria($criteria);

        $timer = new Timer();

        try {
            $cursor = $this->db->{$collection}->find($criteria);

            // apply params
            if (isset($params['start'])) {
                $cursor->skip(intval($params['start']));
            }

            if (isset($params['limit'])) {
                $cursor->limit(intval($params['limit']));
            }

            if (isset($params['orderBy'])) {
                $cursor->sort(array(
                    $params['orderBy'] => (isset($params['orderDir']) && strtolower($params['orderDir']) === 'desc') ? -1 : 1
                ));
            }
        } catch (MongoException $e) {
            $this->logQuery($collection, 'find', $criteria, array(), true);
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // parse the results
        $result = array();
        while($cursor->hasNext()) {
            $result[] = $cursor->getNext();
        }

        // log this query
        $this->logQuery($collection, 'find', $criteria, array(
            'executionTime' => $timer->stop(),
            'affected' => $cursor->count(true)
        ));

        return $result;
    }

    /**
     * Performs a SELECT COUNT(*) query on the given collection.
     * 
     * @param string $collection Name of the collection to select from.
     * @param CriteriaExpression $criteria Criteria on which to select.
     * @param array $params [optional] Paramaters of the select (like order, start, limit, groupby, etc.)
     * @return array
     */
    public function count($collection, CriteriaExpression $criteria = null, array $params = array()) {
        $criteria = $this->parseCriteria($criteria);

        $timer = new Timer();

        $params = array_merge(array(
            'limit' => 0,
            'skip' => 0
        ), $params);

        try {
            $result = $this->db->{$collection}->count($criteria, $params['limit'], $params['skip']);
        } catch (MongoException $e) {
            $this->logQuery($collection, 'count', $criteria, array(), true);
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->logQuery($collection, 'count', $criteria, array(
            'executionTime' => $timer->stop()
        ));

        return $result;
    }

    /**
     * Performs an INSERT query with the given data to the given collection.
     * 
     * @param string $collection Name of the collection to insert into.
     * @param array $data Data that should be inserted.
     * @return string|null ID of the inserted object.
     * 
     * @throws StoreQueryErrorException When there was an error inserting the data.
     */
    public function add($collection, array $data) {
        $timer = new Timer();

        // already reserve the ID as we want to store it in a duplicated "id" field
        $id = new MongoId();
        $data['id'] = (string)$id;
        $data['_id'] = $id;

        try {
            $result = $this->db->{$collection}->insert($data);
        } catch (MongoException $e) {
            $this->logQuery($collection, 'insert', array(), array(
                'data' => $data
            ), true);
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->logQuery($collection, 'insert', array(), array(
            'executionTime' => $timer->stop(),
            'data' => $data
        ));

        if (!$result) {
            return null;
        }

        return isset($data['_id']) ? (string) $data['_id'] : null;
    }

    /**
     * Performs an UPDATE query on the given collection with the given criteria.
     * 
     * @param string $collection Name of the collection which to update.
     * @param CriteriaExpression $criteria Criteria on which to update.
     * @param array $data Data that should be updated.
     * 
     * @throws StoreQueryErrorException WHen there was an error updating the data.
     */
    public function update($collection, CriteriaExpression $criteria = null, array $data) {
        $criteria = $this->parseCriteria($criteria);

        // check if multiple update is possible
        $multiple = false;
        foreach($criteria as $key => $value) {
            if ($key[0] === '$') {
                $multiple = true;
                break;
            }
        }

        $timer = new Timer();

        try {
            $info = $this->db->{$collection}->update($criteria, $data, array(
                'multiple' => $multiple // always allow multi updates
            ));
        } catch (MongoException $e) {
            $this->logQuery($collection, 'update', $criteria, array(
                'data' => $data
            ), true);
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->logQuery($collection, 'update', $criteria, array(
            'data' => $data,
            'executionTime' => $timer->stop(),
            'affected' => (is_array($info)) ? $info['n'] : 'unknown'
        ));
    }

    /**
     * Performs a DELETE query on the given collection with the given criteria.
     * 
     * @param string $collection Name of the collection from which to delete.
     * @param CriteriaExpression $criteria Criteria on which to delete.
     */
    public function delete($collection, CriteriaExpression $criteria = null) {
        $criteria = $this->parseCriteria($criteria);

        $timer = new Timer();

        try {
            $info = $this->db->{$collection}->remove($criteria);
        } catch (MongoException $e) {
            $this->logQuery($collection, 'delete', $criteria, array(), true);
            throw new StoreQueryErrorException('MongoDB: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->logQuery($collection, 'delete', $criteria, array(
            'executionTime' => $timer->stop(),
            'affected' => (is_array($info)) ? $info['n'] : 'unknown'
        ));
    }

    /**
     * Returns empty array as MongoDB doesn't have pre-defined structures of data.
     * 
     * @param string $collection Name of the collection to get structure for.
     * @return array
     */
    public function structure($collection) {
        return array();
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Logs the given PDO statement (database query) to the available logger.
     * 
     * @param string $collection Name of the collection on which the query was executed.
     * @param string $type Type of the query performed, e.g. "find", "remove", "update", "insert", "count".
     * @param array $criteria Array of criteria used in the query.
     * @param array $context [optional] Context of the query (like execution time).
     * @param bool $error [optional] Has an error occurred on this query? Default: false.
     */
    public function logQuery($collection, $type, array $criteria, array $context = array(), $error = false) {
        // if not really logging then don't bother
        if ($this->logger instanceof NullLogger) {
            return;
        }

        $message = $type .' @ '. $collection .': '. json_encode($criteria);

        $context = array_merge($context, array(
            'collection' => $collection,
            'criteria' => $criteria,
            'type' => $type,
            '_tags' => array(
                'mongodb', $type, $collection
            )
        ));

        // add trace
        $knitDir = realpath(dirname(__FILE__) .'/../../../');
        $trace = Debugger::getPrettyTrace(debug_backtrace());

        $caller = null;

        foreach($trace as $call) {
            // first occurence of a file that is outside of Knit means close to getting the caller
            if (stripos($call['file'], $knitDir) !== 0) {
                $caller = $call;
                break;
            }
        }

        if ($caller) {
            $context['caller'] = $caller;
        }

        // if error occurred then log as error
        if ($error) {
            $this->logger->error($message, $context);

        // otherwise just log normally
        } else {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Parses the CriteriaExpression into MongoDB criteria array.
     * 
     * @param CriteriaExpression $criteria Criteria expression to be parsed.
     * @param bool $asCollection [optional] Should this criteria be parsed as a collection? Required for OR logic. For internal use. Default: false.
     * @return array
     */
    protected function parseCriteria(CriteriaExpression $criteria = null, $asCollection = false) {
        if (is_null($criteria)) {
            return array();
        }

        $result = array();

        foreach($criteria->getCriteria() as $criterium) {
            if ($criterium instanceof CriteriaExpression) {
                $logic = $criterium->getLogic() === KnitOptions::LOGIC_OR ? '$or' : '$and';
                $result[$logic] = $this->parseCriteria($criterium, true);
                continue;
            }

            $field = $criterium->getField();
            $criteriumValue = $criterium->getValue();

            if ($field === 'id') {
                $field = '_id';

                if (is_array($criteriumValue)) {
                    foreach($criteriumValue as $i => $id) {
                        $criteriumValue[$i] = new MongoId($id);
                    }
                } else {
                    $criteriumValue = new MongoId($criteriumValue);
                }
            }

            // figure out an operator
            switch($criterium->getOperator()) {
                case FieldValue::OPERATOR_EQUALS:
                    $value = $criteriumValue;
                    break;

                case FieldValue::OPERATOR_NOT:
                    $value = array('$ne' => $criteriumValue);
                    break;

                case FieldValue::OPERATOR_IN:
                    // if the value is empty or is not an array then replace whole statement with a 0 (false) so it never matches
                    /* @todo needs to be implemented and tested
                    if (!is_array($values) || empty($values)) {
                        $sql = '0';
                        break;
                    }
                    */
                    $value = array('$in' => $criteriumValue);
                    break;

                case FieldValue::OPERATOR_GREATER_THAN:
                    $value = array('$gt' => $criteriumValue);
                    break;

                case FieldValue::OPERATOR_GREATER_THAN_EQUAL:
                   $value = array('$gte' => $criteriumValue);
                    break;

                case FieldValue::OPERATOR_LOWER_THAN:
                    $value = array('$lt' => $criteriumValue);
                    break;

                case FieldValue::OPERATOR_LOWER_THAN_EQUAL:
                    $value = array('$lte' => $criteriumValue);
                    break;

                // if it hasn't been handled by the above then throw an exception
                default:
                    throw new InvalidOperatorException('MongoDBStore cannot handle operator "'. trim($criterium->getOperator(), '_') .'" used for "'. $criterium->getField() .'" column. Either because this operator is not supported by MongoDB or it has not been implemented in Knit yet.');
            }

            if ($asCollection) {
                $result[] = array(
                    $field => $value
                );
                continue;
            }

            // if already set an expression for this field then append there
            if (isset($result[$field])) {
                // if expression for this field isn't an array then it needs to be converted to an equals expression
                if (!is_array($result[$field])) {
                    $result[$field] = array('$all' => array($result[$field])); // using '$all' here as there's no '$eq' operator in Mongo
                }

                // fix analogusly for the value itself
                if ($criterium->getOperator() === FieldValue::OPERATOR_EQUALS) {
                    $value = array('$all' => array($value));
                }

                // do in a foreach to get the operator (which is set to be key)
                foreach($value as $operator => $val) {
                    $result[$field][$operator] = $val;
                }

            } else {
                // no expression for this field have been found yet, so add it
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Sets the logger.
     * 
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

}