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
class Hobbit implements ArraySerializableInterface
{
    /**
     * ID.
     *
     * @var integer
     */
    protected $id;

    /**
     * Name.
     *
     * @var string
     */
    protected $name;

    /**
     * Height.
     *
     * @var integer
     */
    protected $height;

    /**
     * Surname.
     *
     * @var string
     */
    protected $surname;

    /**
     * Sets ID.
     *
     * @param integer $id ID.
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Gets ID.
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

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
     * Sets height.
     *
     * @param integer $height Height.
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }

    /**
     * Gets height.
     *
     * @return integer
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Sets surname.
     *
     * @param string $surname Surname.
     */
    public function setSurname($surname)
    {
        $this->surname = $surname;
    }

    /**
     * Gets surname.
     *
     * @return string
     */
    public function getSurname()
    {
        return $this->surname;
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
        return 'id';
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
        return $this->getId();
    }

    /**
     * Sets an identifier on the object.
     *
     * @param mixed  $identifier Identifier to be set on the object.
     */
    public function identifyWith($identifier)
    {
        $this->setId($identifier);
    }

    /**
     * Loads data from the passed array to the object.
     *
     * @param array $data Data to be loaded.
     */
    public function fromArray(array $data)
    {
        $setters = [
            'id' => 'setId',
            'name' => 'setName',
            'height' => 'setHeight',
            'surname' => 'setSurname'
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
            'id' => 'getId',
            'name' => 'getName',
            'height' => 'getHeight',
            'surname' => 'getSurname'
        ];

        $data = [];
        foreach ($getters as $property => $getter) {
            $data[$property] = $this->{$getter}();
        }

        return $data;
    }
}
