<?php
namespace Knit\Tests\DataMapper\ArraySerializable;

use Knit\Tests\Fixtures;

use Knit\DataMapper\ArraySerializable\ArraySerializer;

/**
 * Tests ArraySerializer.
 *
 * @package    Knit
 * @subpackage DataMapper
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @covers Knit\DataMapper\ArraySerializable\ArraySerializer
 */
class ArraySerializerTest extends \PHPUnit_Framework_TestCase
{
    public function testSupports()
    {
        $dataMapper = new ArraySerializer();

        $this->assertTrue($dataMapper->supports(Fixtures\Hobbit::class));
        $this->assertFalse($dataMapper->supports(Fixtures\Orc::class));
    }

    public function testIdentifier()
    {
        $dataMapper = new ArraySerializer();

        $this->assertEquals('id', $dataMapper->identifier(Fixtures\Hobbit::class));
    }

    public function testIdentify()
    {
        $dataMapper = new ArraySerializer();

        $hobbit = new Fixtures\Hobbit();
        $hobbit->setId(1);

        $this->assertEquals(1, $dataMapper->identify($hobbit));
    }

    public function testIdentifyWith()
    {
        $dataMapper = new ArraySerializer();

        $hobbit = new Fixtures\Hobbit();

        $dataMapper->identifyWith($hobbit, 2);

        $this->assertEquals(2, $hobbit->getId());
    }

    public function testFromArray()
    {
        $dataMapper = new ArraySerializer();

        $hobbit = new Fixtures\Hobbit();
        $this->assertNull($hobbit->getName());
        $this->assertNull($hobbit->getHeight());
        $this->assertNull($hobbit->getSurname());

        $dataMapper->fromArray($hobbit, [
            'name' => 'Frodo',
            'height' => 140,
            'surname' => 'Baggins'
        ]);

        $this->assertEquals('Frodo', $hobbit->getName());
        $this->assertEquals(140, $hobbit->getHeight());
        $this->assertEquals('Baggins', $hobbit->getSurname());
    }

    public function testToArray()
    {
        $dataMapper = new ArraySerializer();

        $hobbit = new Fixtures\Hobbit();
        $hobbit->setId(1);
        $hobbit->setName('Frodo');
        $hobbit->setHeight(140);
        $hobbit->setSurname('Baggins');

        $this->assertEquals([
            'id' => 1,
            'name' => 'Frodo',
            'height' => 140,
            'surname' => 'Baggins'
        ], $dataMapper->toArray($hobbit));
    }
}
