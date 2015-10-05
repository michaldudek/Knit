<?php
namespace Knit\DataMapper;

/**
 * Data Mapper is responsible for populating objects with data from an array (a.k.a. "hydration")
 * and vice versa - converting an object to an array (a.k.a. "extraction").
 *
 * All data mappers used with Knit repositories MUST implement this interface.
 *
 * @package    Knit
 * @subpackage DataMapper
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
interface DataMapperInterface
{
    /**
     * Checks whether or not the data mapper supports the given class.
     *
     * For example by checking if it implements a required interface.
     *
     * @param string $class Class to be checked if it can be mapped with this data mapper.
     *
     * @return boolean
     */
    public function supports($class);

    /**
     * Populates the given object with data from the given array.
     *
     * @param object $object Object to be populated.
     * @param array  $data   Data to populate the object with.
     */
    public function fromArray($object, array $data);

    /**
     * Converts the given object to an array.
     *
     * @param object $object Object to be converted.
     *
     * @return array
     */
    public function toArray($object);
}
