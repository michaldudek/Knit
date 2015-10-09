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
class Elf implements ArraySerializableInterface
{
    /**
     * Name.
     *
     * @var string
     */
    protected $name;

    /**
     * Place.
     *
     * @var string
     */
    protected $place;

    /**
     * Sets name.
     *
     * @param string $name Name.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets place.
     *
     * @param string $place Place.
     */
    public function setPlace($place)
    {
        $this->place = $place;
    }

    /**
     * Gets place.
     *
     * @return string
     */
    public function getPlace()
    {
        return $this->place;
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
        return ['place', 'name'];
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
        return [
            'place' => $this->getPlace(),
            'name' => $this->getName()
        ];
    }

    /**
     * Sets an identifier on the object.
     *
     * @param mixed  $identifier Identifier to be set on the object.
     */
    public function identifyWith($identifier)
    {
        $this->setPlace($identifier['place']);
        $this->setName($identifier['name']);
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
            'place' => 'setPlace'
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
            'place' => 'getPlace'
        ];

        $data = [];
        foreach ($getters as $property => $getter) {
            $data[$property] = $this->{$getter}();
        }

        return $data;
    }
}
