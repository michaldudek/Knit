<?php
/**
 * Event called when data will be about to removed based on the given criteria.
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

class WillDeleteOnCriteria extends AbstractEvent
{

    /**
     * Array of criteria with which data will be searched for in store.
     * 
     * @var array
     */
    private $_criteria = array();

    /**
     * Constructor.
     * 
     * @param array $criteria [optional] Array of criteria with which data will be searched for in store.
     * @param array $params [optional] Array of any additional params with which data will be searched for in store.
     */
    public function __construct(array $criteria = array()) {
        $this->_criteria = $criteria;
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

}