<?php
/**
 * Extension that makes an entity softdeletable, meaning that it will not be removed from the persistent store,
 * rather marked as deleted in a `deleted` property and not possible to be retrieved using standard repositories.
 * 
 * @package Knit
 * @subpackage Extensions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2015, Michał Dudek
 * @license MIT
 */
namespace Knit\Extensions;

use RuntimeException;

use MD\Foundation\Utils\ArrayUtils;

use Knit\Entity\Repository;
use Knit\Events\DidDeleteEntity;
use Knit\Events\WillDeleteEntity;
use Knit\Events\WillDeleteOnCriteria;
use Knit\Events\WillReadFromStore;
use Knit\Extensions\ExtensionInterface;
use Knit\KnitOptions;
use Knit\Knit;

class Softdeletable implements ExtensionInterface
{

    /**
     * Knit.
     * 
     * @var Knit
     */
    protected $knit;

    /**
     * Constructor.
     * 
     * @param Knit $knit Knit.
     */
    public function __construct(Knit $knit) {
        $this->knit = $knit;
    }

    /**
     * Adds the extension to the given repository.
     * 
     * @param Repository $repository
     */
    public function addExtension(Repository $repository) {
        $repository->extendEntityStructure(array(
            'deleted_at' => array(
                'type' => KnitOptions::TYPE_INT,
                'maxLength' => 10,
                'required' => false,
                'default' => 0
            ),
            'deleted' => array(
                'type' => KnitOptions::TYPE_INT,
                'allowedValues' => array(0, 1),
                'required' => false,
                'default' => 0
            )
        ));

        $eventManager = $repository->getEventManager();

        $eventManager->subscribe(WillDeleteEntity::getName(), array($this, 'softDelete'));
        $eventManager->subscribe(WillDeleteOnCriteria::getName(), array($this, 'softDeleteOnCriteria'));
        $eventManager->subscribe(WillReadFromStore::getName(), array($this, 'filterSoftDeleted'));
    }

    /**
     * Responds to an `WillDeleteEntity` event and cancels the action and instead
     * updates the entity with soft deleted parameters.
     * 
     * @param  WillDeleteEntity $event
     * @return
     */
    public function softDelete(WillDeleteEntity $event) {
        $event->preventDefault();

        $entity = $event->getEntity();

        $entity->setDeletedAt(time());
        $entity->setDeleted(1);

        $repository = $this->knit->getRepository($entity);
        $repository->save($entity);

        // still trigger the deleted event
        $repository->getEventManager()->trigger(new DidDeleteEntity($entity));
    }

    public function softDeleteOnCriteria(WillDeleteOnCriteria $event) {
        // @todo
        // this probably needs to prevent the event and then fetch all entities
        // that would match the criteria and then delete them one by one
        // or, alternatively, just update on criteria ?
    }

    /**
     * When reading from a store, this adds a filtering criteria that will prevent
     * fetching soft deleted entities.
     * 
     * @param  WillReadFromStore $event
     */
    public function filterSoftDeleted(WillReadFromStore $event) {
        $event->setCriteria(ArrayUtils::mergeDeep($event->getCriteria(), array('deleted:not' => 1)));
    }
    
}