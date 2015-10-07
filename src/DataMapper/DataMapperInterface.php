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
     * Returns the name of the identifier/primary key of objects of the given class.
     *
     * It may return an array or strings for compound keys.
     *
     * @param string $class Class name.
     *
     * @return string|array
     */
    public function identifier($class);

    /**
     * Identify the given object by returning its identifier/primary key.
     *
     * Should return `null` if the object cannot be identified (e.g. has not yet been persisted in its store and
     * doesn't have an identifier assigned).
     *
     * @param object $object Object to be identified.
     *
     * @return mixed
     */
    public function identify($object);

    /**
     * Sets an identifier on the given object.
     *
     * @param object $object     Object to be identified.
     * @param mixed  $identifier Identifier to be set on the object.
     */
    public function identifyWith($object, $identifier);

    /**
     * Populates the given object with data from the given array.
     *
     * Note: the `$data` array may not contain all object data, but only a subset of it,
     * so the underlying implementation should not assume that.
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
