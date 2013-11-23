<?php
/**
 * Extension that adds created_at and updated_at properties to an entity and sets and updates them automatically
 * during entity's lifecycle.
 * 
 * @package Knit
 * @subpackage Extensions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Extensions;

use Knit\Entity\Repository;
use Knit\Events\WillAddEntity;
use Knit\Events\WillUpdateEntity;
use Knit\Extensions\ExtensionInterface;
use Knit\KnitOptions;

class Timestampable implements ExtensionInterface
{

    /**
     * Adds the extension to the given repository.
     * 
     * @param Repository $repository
     */
    public function addExtension(Repository $repository) {
        $repository->extendEntityStructure(array(
            'created_at' => array(
                'type' => KnitOptions::TYPE_INT,
                'maxLength' => 10,
                'required' => false,
                'default' => 0
            ),
            'updated_at' => array(
                'type' => KnitOptions::TYPE_INT,
                'maxLength' => 10,
                'required' => false,
                'default' => 0
            )
        ));

        $eventManager = $repository->getEventManager();

        $eventManager->subscribe(WillAddEntity::getName(), array($this, 'setCreatedAtOnAdd'));
        $eventManager->subscribe(WillUpdateEntity::getName(), array($this, 'setUpdatedAtOnUpdate'));
    }

    /**
     * Sets creation and update times on the entity that is being added to the current time.
     * 
     * @param WillAddEntity $event Event called right before the entity is persisted in a store.
     */
    public function setCreatedAtOnAdd(WillAddEntity $event) {
        $entity = $event->getEntity();
        $entity->setCreatedAt(time());
        $entity->setUpdatedAt(time());
    }

    /**
     * Sets update time on the entity that is being updated to the current time.
     * 
     * @param WillUpdateEntity $event Event called right before the entity is updated in a store.
     */
    public function setUpdatedAtOnUpdate(WillUpdateEntity $event) {
        $entity = $event->getEntity();
        $entity->setUpdatedAt(time());
    }

}