<?php
/**
 * Extension that allows for easy use of global criteria filters that apply to all actions on the repository.
 * 
 * For example if you want to filter all entities by a specific value of their field on EVERY read.
 * 
 * @package Knit
 * @subpackage Extensions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Extensions;

use MD\Foundation\Utils\ArrayUtils;

use Knit\Entity\Repository;
use Knit\Events\WillReadFromStore;
use Knit\Events\WillDeleteOnCriteria;
use Knit\Events\WillCreateEntity;
use Knit\Extensions\ExtensionInterface;

class GlobalFilter implements ExtensionInterface
{

    /**
     * Critiera which should be added to original query criteria.
     * 
     * @var array
     */
    protected $filterCriteria;

    /**
     * Any properties that should be set in the entity before its stored in persistent store?
     * 
     * @var array
     */
    protected $properties = array();

    /**
     * Constructor.
     * 
     * @param array $filterCriteria Critiera which should be added to original query criteria.
     * @param array $properties [optional] Any properties that should be set in the entity before its stored in persistent store?
     */
    public function __construct(array $filterCriteria, array $properties = array()) {
        $this->filterCriteria = $filterCriteria;
        $this->properties = $properties;
    }

    /**
     * Adds the extension to the given repository.
     * 
     * @param Repository $repository
     */
    public function addExtension(Repository $repository) {
        $eventManager = $repository->getEventManager();

        $eventManager->subscribe(WillReadFromStore::getName(), array($this, 'addFilterToCriteriaOnRead'));
        $eventManager->subscribe(WillDeleteOnCriteria::getName(), array($this, 'addFilterToCriteriaOnDelete'));
        $eventManager->subscribe(WillCreateEntity::getName(), array($this, 'setFilterEntityPropertiesOnCreate'));
    }

    /**
     * Adds the filter to read criteria on WillReadFromStore event.
     * 
     * @param WillReadFromStore $store
     */
    public function addFilterToCriteriaOnRead(WillReadFromStore $event) {
        $event->setCriteria(ArrayUtils::mergeDeep($event->getCriteria(), $this->filterCriteria));
    }

    /**
     * Adds the filter to read criteria on WillDeleteOnCriteria event.
     * 
     * @param WillDeleteOnCriteria $store
     */
    public function addFilterToCriteriaOnDelete(WillDeleteOnCriteria $event) {
        $event->setCriteria(ArrayUtils::mergeDeep($event->getCriteria(), $this->filterCriteria));
    }

    /**
     * Adds any required properties to the entity data right before it's created from the data.
     * 
     * @param WillCreateEntity $event Event triggered right before creation of an entity from data.
     */
    public function setFilterEntityPropertiesOnCreate(WillCreateEntity $event) {
        $data = array_merge($event->getData(), $this->properties);
        $event->setData($data);
    }

}