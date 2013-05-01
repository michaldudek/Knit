<?php
/**
 * Event called after getting results from a persistent store and about to instantiate an entity.
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

class WillBindDataToEntity extends AbstractEvent
{

    /**
     * Data with which the entity will be instantiated.
     * 
     * @var array
     */
    private $_data = array();

    /**
     * Constructor.
     * 
     * @param array $data [optional] Data with which the entity will be instantiated.
     */
    public function __construct(array $data = array()) {
        $this->_data = $data;
    }

    /**
     * Sets the data with which the entity will be instantiated.
     * 
     * @param array $data
     */
    public function setData(array $data) {
        $this->_data = $data;
    }

    /**
     * Returns the data with which the entity will be instantiated.
     * 
     * @return array
     */
    public function getData() {
        return $this->_data;
    }

}