<?php
/**
 * Event called when an entity was updated in persistent store.
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

class DidUpdateEntity extends AbstractEvent
{

    /**
     * The entity for which the operation did occur.
     * 
     * @var AbstractEntity
     */
    private $_entity;

    /**
     * Constructor.
     * 
     * @param AbstractEntity Entity for which the operation will occur.
     */
    public function __construct(AbstractEntity $entity) {
        $this->_entity = $entity;
    }

    /**
     * Returns the entity for which the operation did occur.
     * 
     * @return AbstractEntity
     */
    public function getEntity() {
        return $this->_entity;
    }

}