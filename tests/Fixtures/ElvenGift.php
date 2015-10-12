<?php
namespace Knit\Tests\Fixtures;

use Knit\DataMapper\ArraySerializable\ArraySerializableInterface;

/**
 * Fixture class.
 *
 * @package    Knit
 * @subpackage DataMapper
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class ElvenGift implements ArraySerializableInterface
{
    /**
     * Gift name.
     *
     * @var string
     */
    protected $name;

    /**
     * Owner name.
     *
     * @var string
     */
    protected $ownerName;

    /**
     * Constructor.
     *
     * @param string $name      Gift name.
     * @param string $ownerName Owner name.
     */
    public function __construct($name, $ownerName)
    {
        $this->name = $name;
        $this->ownerName = $ownerName;
    }

    /**
     * Returns the gift name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the owner name.
     *
     * @return string
     */
    public function getOwnerName()
    {
        return $this->ownerName;
    }

    /**
     * Returns the name of the identifier/primary key of this object.
     *
     * It may return an array or strings for compound keys.
     *
     * @return string|array
     */
    public function identifier()
    {
        return 'name';
    }

    /**
     * Identify the object by returning its identifier/primary key.
     *
     * Should return `null` if the object cannot be identified (e.g. has not yet been persisted in its store and
     * doesn't have an identifier assigned).
     *
     * @return mixed
     */
    public function identify()
    {
        return $this->getName();
    }

    /**
     * Sets an identifier on the object.
     *
     * @param mixed  $identifier Identifier to be set on the object.
     */
    public function identifyWith($identifier)
    {
        $this->setName($identifier);
    }

    /**
     * Loads data from the passed array to the object.
     *
     * @param array $data Data to be loaded.
     */
    public function fromArray(array $data)
    {
        $setters = [
            'name' => 'setName',
            'owner_name' => 'setOwnerName'
        ];

        foreach ($data as $property => $value) {
            if (isset($setters[$property])) {
                $this->{$setters[$property]}($value);
            }
        }
    }

    /**
     * Converts the object to array and returns it.
     *
     * @return array
     */
    public function toArray()
    {
        $getters = [
            'name' => 'getName',
            'owner_name' => 'getOwnerName'
        ];

        $data = [];
        foreach ($getters as $property => $getter) {
            $data[$property] = $this->{$getter}();
        }

        return $data;
    }
}
