<?php
/**
 * Class that contains information about a query, for logging purposes.
 * 
 * @package Knit
 * @subpackage Debug
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Debug;

class QueryInfo
{

    const CREATE_TYPE = 'create';
    const READ_TYPE = 'read';
    const UPDATE_TYPE = 'update';
    const DELETE_TYPE = 'delete';

    /**
     * String representation of the query (e.g. SQL or JSON).
     * 
     * @var string
     */
    protected $query;

    /**
     * CRUD type of the query (or other).
     * 
     * @var string
     */
    protected $type;

    /**
     * Microtime when the query started.
     * 
     * @var float
     */
    protected $startTime;

    /**
     * How long did the query take in seconds.
     * 
     * @var float
     */
    protected $time = 0;

    /**
     * How many rows or documents were affected?
     * 
     * @var int
     */
    protected $affected = null;

    /**
     * Caller of the query (file and location).
     * 
     * @var string
     */
    protected $caller;

    /**
     * Constructor.
     * 
     * @param string $query String representation of the query (e.g. SQL or JSON).
     * @param string $type CRUD type of the query (or other).
     * @param float $startTime Microtime when the query started.
     * @param float $time How long did the query take in seconds.
     * @param int $affected [optional] How many rows or documents were affected?
     */
    public function __construct($query, $type, $startTime, $time, $affected = null) {
        $this->query = $query;
        $this->type = $type;
        $this->startTime = $startTime;
        $this->time = $time;
        $this->affected = $affected;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns string representation of the query (e.g. SQL or JSON).
     * 
     * @return string
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * Returns CRUD type of the query (or other).
     * 
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Returns microtime when the query started.
     * 
     * @return float
     */
    public function getStartTime() {
        return $this->startTime;
    }

    /**
     * Returns how long did the query take in seconds.
     * 
     * @return float
     */
    public function getTime() {
        return $this->time;
    }

    /**
     * Returns how many rows or documents were affected?
     * 
     * @return int
     */
    public function getAffected() {
        return $this->affected;
    }

    /**
     * Sets caller of the query (file and location).
     * 
     * @param string $caller
     */
    public function setCaller($caller) {
        $this->caller = $caller;
    }

    /**
     * Returns caller of the query (file and location).
     * 
     * @return string
     */
    public function getCaller() {
        return $this->caller;
    }

}