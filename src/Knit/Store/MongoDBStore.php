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
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Exceptions\StoreQueryErrorException;
use Knit\Store\StoreInterface;
use Knit\KnitOptions;
use Knit\Knit;

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
     * Constructor.
     * 
     * @param string $hostname MongoDB server host name.
     * @param string $username MongoDB server user name.
     * @param string $password MongoDB server user password.
     * @param string $database MongoDB database name.
     * @param int $port MongoDB server port number.
     */
    public function __construct($hostname, $username, $password, $database, $port = null) {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;

        $this->logger = new NullLogger();
    }

    /*****************************************************
     * STORE INTERFACE IMPLEMENTATION
     *****************************************************/
    /**
     * Performs a SELECT query on the given collection.
     * 
     * @param string $collection Name of the collection to select from.
     * @param CriteriaExpression $criteria Criteria on which to select.
     * @param array $params [optional] Paramaters of the select (like order, start, limit, groupby, etc.)
     * @return array
     */
    public function find($collection, CriteriaExpression $criteria = null, array $params = array()) {
        throw new NotImplementedException();
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
        throw new NotImplementedException();
    }

    /**
     * Performs an UPDATE query on the given collection with the given criteria.
     * 
     * @param string $collection Name of the collection which to update.
     * @param CriteriaExpression $criteria Criteria on which to update.
     * @param array $data Data that should be updated.
     */
    public function update($collection, CriteriaExpression $criteria = null, array $data) {
        throw new NotImplementedException();
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