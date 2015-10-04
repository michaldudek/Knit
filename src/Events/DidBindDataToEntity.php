<?php
/**
 * Event called when data from a persistent store have been bound to entity.
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

class DidBindDataToEntity extends AbstractEvent
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