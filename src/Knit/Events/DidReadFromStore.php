<?php
/**
 * Event called when entities have been read from a persistent store.
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

use Knit\Entity\AbstractEntity;

class DidReadFromStore extends AbstractEvent
{

    /**
     * Collection array of entities that have been read from persistent store.
     * 
     * @var array
     */
    private $_entities = array();

    /**
     * Constructor.
     * 
     * @param array $entities [optional] Collection array of entities that have been read from persistent store.
     */
    public function __construct(array $entities = array()) {
        $this->_entities = $entities;
    }

    /**
     * Returns collection array of entities that have been read from persistent store.
     * 
     * @return array
     */
    public function getEntities() {
        return $this->_entities;
    }

}