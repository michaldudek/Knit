<?php
namespace Knit\Tests;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\DataMapper\ArraySerializable\ArraySerializer;
use Knit\Store\StoreInterface;
use Knit\Events;
use Knit\Knit;

use Knit\Repository;

/**
 * Tests repository.
 *
 * @package    Knit
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @covers Knit\Repository
 */
class RepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test properly constructing the repository and some getters.
     */
    public function testConstructing()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $this->assertEquals('\\'. $mocks['objectClass'], $repository->getObjectClass());
        $this->assertEquals($mocks['collection'], $repository->getCollection());
        $this->assertSame($mocks['store'], $repository->getStore());
        $this->assertSame($mocks['dataMapper'], $repository->getDataMapper());
        $this->assertSame($mocks['eventDispatcher'], $repository->getEventDispatcher());
    }

    /**
     * Tests what happens if an inexistent class is passed to the constructor.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testConstructingForFakeClass()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = 'NoSuchThingAsThisClass';
        $repository = $this->provideRepository($mocks);
    }

    /**
     * Tests what happens if the data mapper and the desired class are incompatible.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testConstructingForIncompatibleClass()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Orc::class;
        $repository = $this->provideRepository($mocks);
    }

    /**
     * Tests the ::find method.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search params.
     * @param array $expected Expected store results.
     */
    public function testFind()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $criteria = ['height:lte' => 150];
        $params = ['orderBy' => 'height'];
        $expected = [
            ['name' => 'Frodo'],
            ['name' => 'Sam'],
            ['name' => 'Merry'],
            ['name' => 'Pippin'],
            ['name' => 'Gimli']
        ];

        // make sure that store is properly called
        $mocks['store']->expects($this->once())
            ->method('find')
            ->with(
                $mocks['collection'],
                $this->isInstanceOf(CriteriaExpression::class),
                $params
            )
            ->will($this->returnValue($expected));

        // make sure events are dispatched
        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_READ, $this->isInstanceOf(Events\CriteriaEvent::class))
            ->will($this->returnArgument(1));
        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_READ, $this->isInstanceOf(Events\ResultsEvent::class))
            ->will($this->returnArgument(1));

        $result = $repository->find($criteria, $params);

        $this->assertInternalType('array', $result);
        $this->assertCount(count($expected), $result);

        foreach ($result as $i => $item) {
            $this->assertInstanceOf($mocks['objectClass'], $item);
            $itemArray = $item->toArray();
            foreach ($expected[$i] as $property => $value) {
                $this->assertEquals($itemArray[$property], $value);
            }
        }
    }

    /**
     * Tests altering criteria on the find method by using event listeners.
     */
    public function testFindAlteringCriteria()
    {
        $mocks = $this->provideMocks();
        
        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();

        $repository = $this->provideRepository($mocks);

        $criteria = ['height' => 150];
        $params = [];
        $newCriteria = array_merge($criteria, ['deleted' => 0]);
        $newParams = ['orderBy' => 'height', 'orderDir' => Knit::ORDER_DESC];

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_WILL_READ,
            function (Events\CriteriaEvent $event) use ($newCriteria, $newParams) {
                $event->setCriteria($newCriteria);
                $event->setParams($newParams);
            }
        );

        $mocks['store']->expects($this->once())
            ->method('find')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($newCriteria) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);

                    foreach ($criteriaExpression->getCriteria() as $i => $criterium) {
                        $this->assertInstanceOf(PropertyValue::class, $criterium);
                        $this->assertEquals($newCriteria[$criterium->getProperty()], $criterium->getValue());
                    }

                    return true;
                }),
                $newParams
            )
            ->will($this->returnValue([]));

        $repository->find($criteria, $params);
    }

    /**
     * Tests altering find results by using event listeners.
     */
    public function testFindAlteringResults()
    {
        $mocks = $this->provideMocks();
        
        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();

        $repository = $this->provideRepository($mocks);

        $newResults = [
            $this->provideHobbit(['name' => 'Frodo']),
            $this->provideHobbit(['name' => 'Merry']),
            $this->provideHobbit(['name' => 'Pippin'])
        ];

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_DID_READ,
            function (Events\ResultsEvent $event) use ($newResults) {
                $event->setResults($newResults);
            }
        );

        $mocks['store']->expects($this->once())
            ->method('find')
            ->will($this->returnValue([]));

        $results = $repository->find();
        $this->assertEquals($results, $newResults);
    }

    /**
     * Tests finding objects by their identifiers.
     */
    public function testFindByIdentifiers()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepositoryStub($mocks, ['find']);

        $params = ['orderBy' => 'name'];
        $identifiers = [4, 8, 15, 16, 23, 42];

        $repository->expects($this->once())
            ->method('find')
            ->with(['id' => $identifiers], $params);

        $repository->findByIdentifiers($identifiers, $params);
    }

    /**
     * Tests finding objects by their compound identifiers.
     */
    public function testFindByCompoundIdentifiers()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Elf::class;
        $repository = $this->provideRepositoryStub($mocks, ['find']);

        $identifiers = [
            ['name' => 'Legolas', 'place' => 'Mirkwood'],
            ['name' => 'Elrond', 'place' => 'Rivendell'],
            ['name' => 'Galadriel', 'place' => 'Lorien']
        ];
        $params = ['orderBy' => 'place'];

        $repository->expects($this->once())
            ->method('find')
            ->with(
                [
                    Knit::LOGIC_OR => [
                        ['name' => 'Legolas', 'place' => 'Mirkwood'],
                        ['name' => 'Elrond', 'place' => 'Rivendell'],
                        ['name' => 'Galadriel', 'place' => 'Lorien']
                    ]
                ],
                $params
            );

        $repository->findByIdentifiers($identifiers, $params);
    }

    /**
     * Tests finding one object.
     */
    public function testFindOne()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepositoryStub($mocks, ['find']);

        $criteria = ['name' => 'Bilbo'];
        $params = ['orderBy' => 'height'];

        $expected = $this->provideHobbit(['name' => 'Bilbo']);

        $repository->expects($this->once())
            ->method('find')
            ->with($criteria, array_merge($params, ['limit' => 1]))
            ->will($this->returnValue([$expected]));

        $result = $repository->findOne($criteria, $params);

        $this->assertSame($expected, $result);
    }

    public function testFindOneByIdentifier()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepositoryStub($mocks, ['findOne']);

        $identifier = 111;
        $expected = $this->provideHobbit(['name' => 'Bilbo']);

        $repository->expects($this->once())
            ->method('findOne')
            ->with(['id' => $identifier])
            ->will($this->returnValue($expected));

        $result = $repository->findOneByIdentifier($identifier);

        $this->assertSame($expected, $result);
    }

    public function testFindOneByCompoundIdentifier()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Elf::class;
        $repository = $this->provideRepositoryStub($mocks, ['findOne']);

        $identifier = ['name' => 'Legolas', 'place' => 'Mirkwood'];

        $repository->expects($this->once())
            ->method('findOne')
            ->with($identifier)
            ->will($this->returnValue(null));

        $result = $repository->findOneByIdentifier($identifier);
        $this->assertNull($result);
    }

    /**
     * Provides magic method names and generated filters.
     *
     * @return array
     */
    public function provideMagicMethodNames()
    {
        return [
            [
                'method' => 'findBySurname',
                'arguments' => ['Baggins', ['orderBy' => 'height']],
                'filters' => ['surname' => 'Baggins'],
                'params' => ['orderBy' => 'height']
            ],
            [
                'method' => 'findByHairStyle',
                'arguments' => [['curly', 'bald']],
                'filters' => ['hair_style' => ['curly', 'bald']],
                'params' => []
            ],
            [
                'method' => 'findByName',
                'arguments' => [],
                'filters' => [],
                'params' => [],
                'expectedException' => \InvalidArgumentException::class
            ],
            [
                'method' => 'findByName',
                'arguments' => ['Bilbo', 111],
                'filters' => ['name' => 'Bilbo'],
                'params' => [],
                'expectedException' => \InvalidArgumentException::class
            ],
            [
                'method' => 'castItIntoTheFire',
                'arguments' => ['The Ring'],
                'filters' => [],
                'params' => [],
                'expectedException' => \BadMethodCallException::class
            ]
        ];
    }

    /**
     * Tests generating search filters based on magic method names.
     *
     * @param string $method    Magic method name.
     * @param array  $arguments Method arguments.
     * @param array  $filters   Generated filters.
     * @param array  $params    Any params.
     *
     * @dataProvider provideMagicMethodNames
     */
    public function testFindByMagicMethod(
        $method,
        array $arguments,
        array $filters,
        array $params,
        $expectedException = null
    ) {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepositoryStub($mocks, ['find']);

        if ($expectedException) {
            $this->setExpectedException($expectedException);
        } else {
            $expectedResult = [
                $this->provideHobbit(['name' => 'Merry']),
                $this->provideHobbit(['name' => 'Pippin'])
            ];

            $repository->expects($this->once())
                ->method('find')
                ->with($filters, $params)
                ->will($this->returnValue($expectedResult));
        }

        $result = call_user_func_array([$repository, $method], $arguments);

        if (!$expectedException) {
            $this->assertEquals($expectedResult, $result);
        }
    }

    /**
     * Provides magic method names and generated filters.
     */
    public function provideMagicMethodNamesForFindOne()
    {
        return [
            [
                'method' => 'findOneByName',
                'arguments' => ['Baggins', ['orderBy' => 'height']],
                'filters' => ['name' => 'Baggins'],
                'params' => ['orderBy' => 'height']
            ],
            [
                'method' => 'findOneByHairStyle',
                'arguments' => [['curly', 'bald']],
                'filters' => ['hair_style' => ['curly', 'bald']],
                'params' => []
            ]
        ];
    }

    /**
     * Tests generating search filters based on magic method names.
     *
     * @dataProvider provideMagicMethodNamesForFindOne
     */
    public function testFindOneByMagicMethod($method, array $arguments, array $filters, array $params)
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepositoryStub($mocks, ['findOne']);

        $expectedResult = $this->provideHobbit(['name' => 'Sam']);

        $repository->expects($this->once())
            ->method('findOne')
            ->with($filters, $params)
            ->will($this->returnValue($expectedResult));

        $result = call_user_func_array([$repository, $method], $arguments);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * Tests counting.
     */
    public function testCount()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $criteria = ['height:lte' => 150];
        $params = ['orderBy' => 'height'];
        $expected = 4;

        // make sure that store is properly called
        $mocks['store']->expects($this->once())
            ->method('count')
            ->with(
                $mocks['collection'],
                $this->isInstanceOf(CriteriaExpression::class),
                $params
            )
            ->will($this->returnValue($expected));

        // make sure events are dispatched
        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_READ, $this->isInstanceOf(Events\CriteriaEvent::class))
            ->will($this->returnArgument(0));

        $result = $repository->count($criteria, $params);

        $this->assertEquals($expected, $result);
    }

    /**
     * Tests that events can alter search criteria on count.
     */
    public function testCountAlteringCriteria()
    {
        $mocks = $this->provideMocks();
        
        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();

        $repository = $this->provideRepository($mocks);

        $criteria = ['height' => 150];
        $params = [];
        $newCriteria = array_merge($criteria, ['deleted' => 0]);
        $newParams = ['orderBy' => 'height', 'orderDir' => Knit::ORDER_DESC];

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_WILL_READ,
            function (Events\CriteriaEvent $event) use ($newCriteria, $newParams) {
                $event->setCriteria($newCriteria);
                $event->setParams($newParams);
            }
        );

        $mocks['store']->expects($this->once())
            ->method('count')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($newCriteria) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);

                    foreach ($criteriaExpression->getCriteria() as $i => $criterium) {
                        $this->assertInstanceOf(PropertyValue::class, $criterium);
                        $this->assertEquals($newCriteria[$criterium->getProperty()], $criterium->getValue());
                    }

                    return true;
                }),
                $newParams
            )
            ->will($this->returnValue(0));

        $repository->count($criteria, $params);
    }

    /**
     * Tests saving a completely new object in the store - and also proper order of events dispatching.
     */
    public function testSaveUnstoredObject()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $insertId = 5;
        $hobbit = $this->provideHobbit(['name' => 'Frodo']);

        $mocks['store']->expects($this->once())
            ->method('add')
            ->with($mocks['collection'], $hobbit->toArray())
            ->will($this->returnValue($insertId));

        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_ADD, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(2))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_ADD, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(3))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $this->assertNull($hobbit->getId());

        $repository->save($hobbit);

        // also make sure the id was properly set
        $this->assertEquals($insertId, $hobbit->getId());

        return $hobbit;
    }

    /**
     * Tests saving a previously stored object.
     *
     * @depends testSaveUnstoredObject
     */
    public function testSaveStoredObject($hobbit)
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $mocks['store']->expects($this->once())
            ->method('update')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($hobbit) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);
                    $criteria = $criteriaExpression->getCriteria();
                    $this->assertCount(1, $criteria);
                    $criterium = current($criteria);
                    $this->assertEquals('id', $criterium->getProperty());
                    $this->assertEquals(PropertyValue::OPERATOR_EQUALS, $criterium->getOperator());
                    $this->assertEquals($hobbit->getId(), $criterium->getValue());
                    return true;
                }),
                $hobbit->toArray()
            );

        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_UPDATE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(2))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_UPDATE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(3))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $repository->save($hobbit);
    }

    /**
     * Tests altering an object during save process.
     */
    public function testSaveAlteringObject()
    {
        $mocks = $this->provideMocks();

        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();
        
        $repository = $this->provideRepository($mocks);

        $insertId = 5;
        $hobbit = $this->provideHobbit(['name' => 'Frodo']);
        $this->assertNull($hobbit->getSurname());
        $this->assertNull($hobbit->getHeight());

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_WILL_SAVE,
            function (Events\ObjectEvent $event) {
                $object = $event->getObject();
                $object->setSurname('Baggins');
            }
        );

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_DID_SAVE,
            function (Events\ObjectEvent $event) {
                $object = $event->getObject();
                $object->setHeight(100);
            }
        );

        $mocks['store']->expects($this->once())
            ->method('add')
            ->with(
                $mocks['collection'],
                [
                    'id' => null,
                    'name' => 'Frodo',
                    'surname' => 'Baggins',
                    'height' => null
                ]
            )
            ->will($this->returnValue($insertId));

        $this->assertNull($hobbit->getId());

        $repository->add($hobbit);

        // also make sure the id was properly set
        $this->assertEquals($insertId, $hobbit->getId());

        // and that height has been altered after object was added
        $this->assertEquals(100, $hobbit->getHeight());
    }

    /**
     * Tests what happens when an uncompatible object is passed to save.
     *
     * @expectedException \LogicException
     */
    public function testSavingInvalidObject()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Orc::class;
        $repository = $this->provideRepository($mocks);

        $repository->save($this->provideHobbit(['name' => 'Frodo']));
    }

    /**
     * Tests adding an object to a store.
     */
    public function testAdd()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $insertId = 5;
        $hobbit = $this->provideHobbit(['name' => 'Frodo']);

        $mocks['store']->expects($this->once())
            ->method('add')
            ->with($mocks['collection'], $hobbit->toArray())
            ->will($this->returnValue($insertId));

        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_ADD, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(2))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_ADD, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(3))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $this->assertNull($hobbit->getId());

        $repository->save($hobbit);

        // also make sure the id was properly set
        $this->assertEquals($insertId, $hobbit->getId());
    }

    /**
     * Tests altering an object before adding it to the database.
     */
    public function testAddAlteringObject()
    {
        $mocks = $this->provideMocks();

        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();
        
        $repository = $this->provideRepository($mocks);

        $insertId = 5;
        $hobbit = $this->provideHobbit(['name' => 'Frodo']);
        $this->assertNull($hobbit->getSurname());
        $this->assertNull($hobbit->getHeight());

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_WILL_ADD,
            function (Events\ObjectEvent $event) {
                $object = $event->getObject();
                $object->setSurname('Baggins');
            }
        );

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_DID_ADD,
            function (Events\ObjectEvent $event) {
                $object = $event->getObject();
                $object->setHeight(100);
            }
        );

        $mocks['store']->expects($this->once())
            ->method('add')
            ->with(
                $mocks['collection'],
                [
                    'id' => null,
                    'name' => 'Frodo',
                    'surname' => 'Baggins',
                    'height' => null
                ]
            )
            ->will($this->returnValue($insertId));

        $this->assertNull($hobbit->getId());

        $repository->add($hobbit);

        // also make sure the id was properly set
        $this->assertEquals($insertId, $hobbit->getId());

        // and that height has been altered after object was added
        $this->assertEquals(100, $hobbit->getHeight());
    }

    /**
     * Tests what happens when an uncompatible object is passed to add.
     *
     * @expectedException \LogicException
     */
    public function testAddingInvalidObject()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Orc::class;
        $repository = $this->provideRepository($mocks);

        $repository->add($this->provideHobbit(['name' => 'Frodo']));
    }

    /**
     * Tests updating an object.
     */
    public function testUpdate()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $hobbit = $this->provideHobbit(['id' => 5, 'name' => 'Frodo']);

        $mocks['store']->expects($this->once())
            ->method('update')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($hobbit) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);
                    $criteria = $criteriaExpression->getCriteria();
                    $this->assertCount(1, $criteria);
                    $criterium = current($criteria);
                    $this->assertEquals('id', $criterium->getProperty());
                    $this->assertEquals(PropertyValue::OPERATOR_EQUALS, $criterium->getOperator());
                    $this->assertEquals($hobbit->getId(), $criterium->getValue());
                    return true;
                }),
                $hobbit->toArray()
            );

        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_UPDATE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(2))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_UPDATE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(3))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_SAVE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $repository->update($hobbit);
    }

    public function testUpdateWithCompoundKey()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Elf::class;
        $repository = $this->provideRepository($mocks);

        $elf = new Fixtures\Elf();
        $elf->setName('Galadriel');
        $elf->setPlace('Lorien');

        $mocks['store']->expects($this->once())
            ->method('update')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($elf) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);
                    $criteria = $criteriaExpression->getCriteria();
                    $this->assertCount(2, $criteria);

                    $i = 0;
                    foreach (['place' => 'getPlace', 'name' => 'getName'] as $property => $getter) {
                        $criterium = $criteria[$i];
                        $this->assertEquals($property, $criterium->getProperty());
                        $this->assertEquals(PropertyValue::OPERATOR_EQUALS, $criterium->getOperator());
                        $this->assertEquals($elf->{$getter}(), $criterium->getValue());
                        $i++;
                    }

                    return true;
                }),
                $elf->toArray()
            );

        $repository->update($elf);
    }

    /**
     * Tests altering an object during update.
     */
    public function testUpdateAlteringObject()
    {
        $mocks = $this->provideMocks();

        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();
        
        $repository = $this->provideRepository($mocks);

        $hobbit = $this->provideHobbit(['id' => 5, 'name' => 'Frodo']);
        $this->assertNull($hobbit->getSurname());
        $this->assertNull($hobbit->getHeight());

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_WILL_UPDATE,
            function (Events\ObjectEvent $event) {
                $object = $event->getObject();
                $object->setSurname('Baggins');
            }
        );

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_DID_UPDATE,
            function (Events\ObjectEvent $event) {
                $object = $event->getObject();
                $object->setHeight(100);
            }
        );

        $mocks['store']->expects($this->once())
            ->method('update')
            ->with(
                $mocks['collection'],
                $this->anything(), // at this point we already know criteria is ok from previous tests
                [
                    'id' => 5,
                    'name' => 'Frodo',
                    'surname' => 'Baggins',
                    'height' => null
                ]
            );

        $repository->update($hobbit);

        // and that height has been altered after object was added
        $this->assertEquals(100, $hobbit->getHeight());
    }

    /**
     * Tests what happens when an uncompatible object is passed to update.
     *
     * @expectedException \LogicException
     */
    public function testUpdatingInvalidObject()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Orc::class;
        $repository = $this->provideRepository($mocks);

        $repository->update($this->provideHobbit(['name' => 'Frodo']));
    }

    /**
     * Tests deleting an object.
     */
    public function testDelete()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $hobbit = $this->provideHobbit(['id' => 5, 'name' => 'Frodo']);

        $mocks['store']->expects($this->once())
            ->method('remove')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($hobbit) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);
                    $criteria = $criteriaExpression->getCriteria();
                    $this->assertCount(1, $criteria);
                    $criterium = current($criteria);
                    $this->assertEquals('id', $criterium->getProperty());
                    $this->assertEquals(PropertyValue::OPERATOR_EQUALS, $criterium->getOperator());
                    $this->assertEquals($hobbit->getId(), $criterium->getValue());
                    return true;
                })
            );

        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_DELETE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_DELETE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $repository->delete($hobbit);
    }

    public function testDeleteWithCompoundKey()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Elf::class;
        $repository = $this->provideRepository($mocks);

        $elf = new Fixtures\Elf();
        $elf->setName('Galadriel');
        $elf->setPlace('Lorien');

        $mocks['store']->expects($this->once())
            ->method('remove')
            ->with(
                $mocks['collection'],
                $this->callback(function ($criteriaExpression) use ($elf) {
                    $this->assertInstanceOf(CriteriaExpression::class, $criteriaExpression);
                    $criteria = $criteriaExpression->getCriteria();
                    $this->assertCount(2, $criteria);

                    $i = 0;
                    foreach (['place' => 'getPlace', 'name' => 'getName'] as $property => $getter) {
                        $criterium = $criteria[$i];
                        $this->assertEquals($property, $criterium->getProperty());
                        $this->assertEquals(PropertyValue::OPERATOR_EQUALS, $criterium->getOperator());
                        $this->assertEquals($elf->{$getter}(), $criterium->getValue());
                        $i++;
                    }

                    return true;
                })
            );

        $mocks['eventDispatcher']->expects($this->at(0))
            ->method('dispatch')
            ->with(Knit::EVENT_WILL_DELETE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $mocks['eventDispatcher']->expects($this->at(1))
            ->method('dispatch')
            ->with(Knit::EVENT_DID_DELETE, $this->isInstanceOf(Events\ObjectEvent::class))
            ->will($this->returnArgument(1));

        $repository->delete($elf);
    }

    /**
     * Tests that a delete operation can be prevented with an event listener.
     */
    public function testDeletePreventing()
    {
        $mocks = $this->provideMocks();

        // need a real event dispatcher
        $mocks['eventDispatcher'] = new EventDispatcher();
        
        $repository = $this->provideRepository($mocks);

        $hobbit = $this->provideHobbit(['id' => 5, 'name' => 'Frodo']);

        $mocks['eventDispatcher']->addListener(
            Knit::EVENT_WILL_DELETE,
            function (Events\ObjectEvent $event) {
                $event->stopPropagation();
            }
        );

        $mocks['store']->expects($this->never())
            ->method('remove');

        $repository->delete($hobbit);
    }

    /**
     * Tests what happens when an uncompatible object is passed to delete.
     *
     * @expectedException \LogicException
     */
    public function testDeletingInvalidObject()
    {
        $mocks = $this->provideMocks();
        $mocks['objectClass'] = Fixtures\Orc::class;
        $repository = $this->provideRepository($mocks);

        $repository->delete($this->provideHobbit(['name' => 'Frodo']));
    }

    /**
     * Tests creating a new managed object from given data.
     */
    public function testCreateWithData()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $hobbit = $repository->createWithData([
            'id' => 4,
            'name' => 'Merry',
            'surname' => 'Brandybuck'
        ]);

        $this->assertEquals(4, $hobbit->getId());
        $this->assertEquals('Merry', $hobbit->getName());
        $this->assertEquals('Brandybuck', $hobbit->getSurname());
        $this->assertNull($hobbit->getHeight());
    }

    /**
     * Tests updating an object with data.
     */
    public function testUpdateWithData()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $hobbit = new Fixtures\Hobbit();
        $hobbit->setId(2);
        $hobbit->setName('Bilbo');
        $hobbit->setSurname('Baggins');
        $hobbit->setHeight(130);

        $repository->updateWithData($hobbit, [
            'name' => 'Frodo',
            'height' => 140
        ]);

        $this->assertEquals(2, $hobbit->getId());
        $this->assertEquals('Frodo', $hobbit->getName());
        $this->assertEquals('Baggins', $hobbit->getSurname());
        $this->assertEquals(140, $hobbit->getHeight());
    }

    /**
     * Tests updating an object that doesn't belong to the repository.
     *
     * @expectedException \LogicException
     */
    public function testUpdateWithDataInvalidObject()
    {
        $mocks = $this->provideMocks();
        $repository = $this->provideRepository($mocks);

        $orc = new Fixtures\Orc();

        $repository->updateWithData($orc, [
            'name' => 'Azog'
        ]);
    }

    /**
     * Provides mocks for tests.
     *
     * @return array
     */
    protected function provideMocks()
    {
        $mocks = [];

        $mocks['objectClass'] = Fixtures\Hobbit::class;
        $mocks['collection'] = 'hobbits';
        $mocks['store'] = $this->getMock(StoreInterface::class);
        $mocks['dataMapper'] = new ArraySerializer();
        $mocks['eventDispatcher'] = $this->getMock(EventDispatcherInterface::class);

        return $mocks;
    }

    /**
     * Provides the repository for tests.
     *
     * @param array $mocks Mocks to be used.
     *
     * @return Repository
     */
    protected function provideRepository(array $mocks)
    {
        return new Repository(
            $mocks['objectClass'],
            $mocks['collection'],
            $mocks['store'],
            $mocks['dataMapper'],
            $mocks['eventDispatcher']
        );
    }

    /**
     * Provides a repository stub with only the chosen methods stubbed.
     *
     * @param array $mocks   Mocks to be used.
     * @param array $methods Methods to be stubbed.
     *
     * @return Repository
     */
    protected function provideRepositoryStub(array $mocks, array $methods)
    {
        $repository = $this->getMockBuilder(Repository::class)
            ->setConstructorArgs(
                [
                    $mocks['objectClass'],
                    $mocks['collection'],
                    $mocks['store'],
                    $mocks['dataMapper'],
                    $mocks['eventDispatcher']
                ]
            )
            ->setMethods($methods)
            ->getMock();

        return $repository;
    }

    /**
     * Builds a fixture object instance from data.
     *
     * @param array $data Data.
     *
     * @return Fixtures\Hobbit
     */
    protected function provideHobbit(array $data)
    {
        $hobbit = new Fixtures\Hobbit();
        $hobbit->fromArray($data);
        return $hobbit;
    }
}
