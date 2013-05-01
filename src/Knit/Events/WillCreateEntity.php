<?php
/**
 * Event called when an entity is about to be created from an array of data.
 * 
 * This can be used to modify the data.
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

class WillCreateEntity extends AbstractEvent
{

    /**
     * Data with which the entity will be created.
     * 
     * @var array
     */
    private $_data = array();

    /**
     * Constructor.
     * 
     * @param array $data [optional] Data with which the entity will be created.
     */
    public function __construct(array $data = array()) {
        $this->_data = $data;
    }

    /**
     * Sets the data with which the entity will be created.
     * 
     * @param array $data
     */
    public function setData(array $data) {
        $this->_data = $data;
    }

    /**
     * Returns the data with which the entity will be created.
     * 
     * @return array
     */
    public function getData() {
        return $this->_data;
    }

}