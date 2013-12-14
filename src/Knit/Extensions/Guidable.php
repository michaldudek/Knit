<?php
/**
 * Extension that adds "guid" property to the entity that will serve as a fake ID.
 * 
 * The guid field is also unique in the repository (but the check is programatic, not a unique index in the database).
 * 
 * The guid is only set when entity is being added. Afterwards you have to manually update it.
 * 
 * It's useful when you want to, for example, hide the actual ID number from the URL.
 * 
 * @package Knit
 * @subpackage Extensions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Extensions;

use RuntimeException;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Utils\StringUtils;

use Knit\Entity\Repository;
use Knit\Events\WillAddEntity;
use Knit\Extensions\ExtensionInterface;
use Knit\KnitOptions;

class Guidable implements ExtensionInterface
{

    /**
     * Adds the extension to the given repository.
     * 
     * @param Repository $repository
     */
    public function addExtension(Repository $repository) {
        $repository->extendEntityStructure(array(
            'guid' => array(
                'type' => KnitOptions::TYPE_STRING,
                'maxLength' => 8,
                'required' => false,
                'default' => ''
            )
        ));

        $eventManager = $repository->getEventManager();

        $eventManager->subscribe(WillAddEntity::getName(), array($this, 'setGuidOnAdd'));
    }

    /**
     * Sets a guid on the entity that is being added to a persistent store.
     * 
     * @param WillAddEntity $event Event triggered when an entity is being added to a persistent store.
     */
    public function setGuidOnAdd(WillAddEntity $event) {
        $entity = $event->getEntity();
        $repository = $entity->_getRepository();

        $guid = StringUtils::random(8);

        $found = $repository->count(array(
            'guid' => $guid
        ));

        // search repository until no result for the slug is found
        while($found) {
            $guid = StringUtils::random(8);

            $found = $repository->count(array(
                'guid' => $guid
            ));
        }

        $entity->setGuid($guid);
    }

}