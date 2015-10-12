<?php
namespace Knit\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event associated with a single object.
 *
 * @package    Knit
 * @subpackage Events
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class ObjectEvent extends Event
{
    /**
     * Object for this event.
     *
     * @var object
     */
    protected $object;

    /**
     * Constructor.
     *
     * @param object $object Object associated with this event.
     */
    public function __construct($object)
    {
        $this->object = $object;
    }

    /**
     * Returns the object associated with this event.
     *
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }
}
