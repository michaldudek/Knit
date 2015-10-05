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
