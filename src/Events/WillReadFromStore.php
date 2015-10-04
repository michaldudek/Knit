<?php
/**
 * Event called when data will be about to read from persistent store.
 * 
 * @package Knit
 * @subpackage Events
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Events;

use Splot\EventManager\AbstractEvent;

class WillReadFromStore extends AbstractEvent
{

    /**
     * Array of criteria with which data will be searched for in store.
     * 
     * @var array
     */
    private $_criteria = array();

    /**
     * Array of any additional params with which data will be searched for in store.
     * 
     * @var array
     */
    private $_params = array();

    /**
     * Constructor.
     * 
     * @param array $criteria [optional] Array of criteria with which data will be searched for in store.
     * @param array $params [optional] Array of any additional params with which data will be searched for in store.
     */
    public function __construct(array $criteria = array(), array $params = array()) {
        $this->_criteria = $criteria;
        $this->_params = $params;
    }

    /**
     * Sets the array of criteria with which data will be searched for in store.
     * 
     * @param array
     */
    public function setCriteria(array $criteria) {
        $this->_criteria = $criteria;
    }

    /**
     * Returns the array of criteria with which data will be searched for in store.
     * 
     * @return array
     */
    public function getCriteria() {
        return $this->_criteria;
    }

    /**
     * Sets the array of any additional params with which data will be searched for in store.
     * 
     * @param array $params
     */
    public function setParams(array $params) {
        $this->_params = $params;
    }

    /**
     * Returns the array of any additional params with which data will be searched for in store.
     * 
     * @return array
     */
    public function getParams() {
        return $this->_params;
    }

}