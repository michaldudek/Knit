<?php
/**
 * Extension that adds "slug" property to the entity and automatically sets it to url friendly value of either
 * "title" or "name" properties (in this order).
 * 
 * The slug field is also unique in the repository (but the check is programatic, not a unique index in the database).
 * 
 * The slug is only set when entity is being added. Afterwards you have to manually update it.
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

class Sluggable implements ExtensionInterface
{

    /**
     * Adds the extension to the given repository.
     * 
     * @param Repository $repository
     */
    public function addExtension(Repository $repository) {
        $repository->extendEntityStructure(array(
            'slug' => array(
                'type' => KnitOptions::TYPE_STRING,
                'required' => false,
                'default' => ''
            )
        ));

        $eventManager = $repository->getEventManager();

        $eventManager->subscribe(WillAddEntity::getName(), array($this, 'setSlugOnAdd'));
    }

    /**
     * Sets a slug on the entity that is being added to a persistent store.
     * 
     * @param WillAddEntity $event Event triggered when an entity is being added to a persistent store.
     */
    public function setSlugOnAdd(WillAddEntity $event) {
        $entity = $event->getEntity();
        $repository = $entity->_getRepository();

        $title = $entity->_hasProperty('title')
            ? $entity->getTitle()
            : ($entity->_hasProperty('name')
                ? $entity->getName()
                : null
            );

        if ($title === null || empty($title)) {
            throw new RuntimeException('Could not determine URL slug source for entity "'. Debugger::getType($entity) .'" (no "title" or "name" properties defined.');
        }

        $rawSlug = StringUtils::urlFriendly($title);
        $slug = $rawSlug;
        $i = 1;

        $found = $repository->count(array(
            'slug' => $slug
        ));

        // search repository until no result for the slug is found
        while($found) {
            $slug = $rawSlug .'-'. $i;

            $found = $repository->count(array(
                'slug' => $slug
            ));

            $i++;
        }

        $entity->setSlug($slug);
    }

}