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
     * MongoDB server host name.
     * 
     * @var string
     */
    protected $hostname;

    /**
     * MongoDB server port number.
     * 
     * @var int
     */
    protected $port;

    /**
     * MongoDB server user name.
     * 
     * @var string
     */
    protected $username;

    /**
     * MongoDB server user password.
     * 
     * @var string
     */
    protected $password;

    /**
     * MongoDB database name.
     * 
     * @var string
     */
    protected $database;

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
                'connect' => false
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
        $repository->setIdProperty('_id');
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

        // parse the results
        $result = array();
        while($cursor->hasNext()) {
            $result[] = $cursor->getNext();
        }

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
        throw new NotImplementedException();
    }

    /**
     * Performs an INSERT query with the given data to the given collection.
     * 
     * @param string $collection Name of the collection to insert into.
     * @param array $data Data that should be inserted.
     * @return string ID of the inserted object.
     */
    public function add($collection, array $data) {
        $result = $this->db->{$collection}->insert($data);
        return $result ? $data['_id'] : null;
    }

    /**
     * Performs an UPDATE query on the given collection with the given criteria.
     * 
     * @param string $collection Name of the collection which to update.
     * @param CriteriaExpression $criteria Criteria on which to update.
     * @param array $data Data that should be updated.
     */
    public function update($collection, CriteriaExpression $criteria = null, array $data) {
        $criteria = $this->parseCriteria($criteria);
        $this->db->{$collection}->update($criteria, $data);
    }

    /**
     * Performs a DELETE query on the given collection with the given criteria.
     * 
     * @param string $collection Name of the collection from which to delete.
     * @param CriteriaExpression $criteria Criteria on which to delete.
     */
    public function delete($collection, CriteriaExpression $criteria = null) {
        throw new NotImplementedException();
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

            // figure out an operator
            switch($criterium->getOperator()) {
                case FieldValue::OPERATOR_EQUALS:
                    $value = $criterium->getValue();
                    break;

                case FieldValue::OPERATOR_NOT:
                    $value = array('$ne' => $criterium->getValue());
                    break;

                case FieldValue::OPERATOR_IN:
                    // if the value is empty or is not an array then replace whole statement with a 0 (false) so it never matches
                    /* @todo needs to be implemented and tested
                    if (!is_array($values) || empty($values)) {
                        $sql = '0';
                        break;
                    }
                    */
                    $value = array('$in' => $criterium->getValue());
                    break;

                case FieldValue::OPERATOR_GREATER_THAN:
                    $value = array('$gt' => $criterium->getValue());
                    break;

                case FieldValue::OPERATOR_GREATER_THAN_EQUAL:
                   $value = array('$gte' => $criterium->getValue());
                    break;

                case FieldValue::OPERATOR_LOWER_THAN:
                    $value = array('$lt' => $criterium->getValue());
                    break;

                case FieldValue::OPERATOR_LOWER_THAN_EQUAL:
                    $value = array('$lte' => $criterium->getValue());
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
                $result[$criterium->getField()] = $value;
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