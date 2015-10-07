<?php
namespace Knit\DataMapper\ArraySerializable;

/**
 * Interface for objects that can serialize and unserialize themselves to and from array.
 *
 * @package    Knit
 * @subpackage DataMapper
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
interface ArraySerializableInterface
{
    /**
     * Returns the name of the identifier/primary key of this object.
     *
     * It may return an array or strings for compound keys.
     *
     * @return string|array
     */
    public function identifier();

    /**
     * Identify the object by returning its identifier/primary key.
     *
     * Should return `null` if the object cannot be identified (e.g. has not yet been persisted in its store and
     * doesn't have an identifier assigned).
     *
     * @return mixed
     */
    public function identify();

    /**
     * Sets an identifier on the object.
     *
     * @param mixed  $identifier Identifier to be set on the object.
     */
    public function identifyWith($identifier);

    /**
     * Loads data from the passed array to the object.
     *
     * @param array $data Data to be loaded.
     */
    public function fromArray(array $data);

    /**
     * Converts the object to array and returns it.
     *
     * @return array
     */
    public function toArray();
}
