<?php
namespace Knit\DataMapper\ArraySerializable;

use MD\Foundation\Debug\Debugger;

use Knit\DataMapper\ArraySerializable\ArraySerializableInterface;
use Knit\DataMapper\DataMapperInterface;

/**
 * Basic data mapper for objects that can serialize and unserialize themselves to and from array.
 *
 * @package    Knit
 * @subpackage DataMapper
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class ArraySerializer implements DataMapperInterface
{
    /**
     * Checks whether or not the data mapper supports the given class.
     *
     * All objects that can be managed by this data mapper MUST implement `ArraySerializableInterface`.
     *
     * @param string $class Class to be checked if it can be mapped with this data mapper.
     *
     * @return boolean
     */
    public function supports($class)
    {
        return Debugger::isImplementing($class, ArraySerializableInterface::class);
    }

    /**
     * Populates the given object with data from the given array.
     *
     * @param object $object Object to be populated.
     * @param array  $data   Data to populate the object with.
     */
    public function fromArray($object, array $data)
    {
        $object->fromArray($data);
    }

    /**
     * Converts the given object to an array.
     *
     * @param object $object Object to be converted.
     *
     * @return array
     */
    public function toArray($object)
    {
        return $object->toArray();
    }
}
