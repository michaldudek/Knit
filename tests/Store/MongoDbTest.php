<?php
namespace Knit\Tests\Store;

use Symfony\Component\EventDispatcher\EventDispatcher;

use MD\Foundation\Utils\ObjectUtils;

use Knit\Tests\Fixtures;

use Knit\Criteria\CriteriaExpression;
use Knit\DataMapper\ArraySerializable\ArraySerializer;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Store\MongoDb\CriteriaParser;
use Knit\Store\MongoDb\Store;
use Knit\Repository;
use Knit\Knit;

/**
 * Tests MongoDb store.
 *
 * @package    Knit
 * @subpackage Store
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class MongoDbTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Repository used for the test.
     *
     * @var Repository
     */
    private $repository;

    /**
     * Test logger for easier debug.
     *
     * @var Fixtures\TestLogger
     */
    private $logger;

    /**
     * Set up the tests.
     */
    public function setUp()
    {
        // DB=mongodb in travis
        // MONGODB=1 in the test Vagrant VM
        if (getenv('DB') !== 'mongodb' && getenv('MONGODB') != '1') {
            $this->markTestSkipped('Not testing MongoDB.');
        }

        try {
            $this->logger = new Fixtures\TestLogger();

            $store = new Store(
                [
                    'username' => getenv('MONGODB_USER'),
                    'password' => getenv('MONGODB_PASSWORD'),
                    'hostname' => getenv('MONGODB_HOST'),
                    'port' => getenv('MONGODB_PORT'),
                    'database' => getenv('MONGODB_DBNAME'),
                ],
                new CriteriaParser(),
                $this->logger
            );

            $this->repository = new Repository(
                Fixtures\Hobbit::class,
                'hobbits',
                $store,
                new ArraySerializer(),
                new EventDispatcher()
            );

        } catch (StoreConnectionFailedException $e) {
            $this->fail('Could not connect to MongoDB: '. $e->getMessage());
        }
    }

    /**
     * Tests throwing a proper exception when there was a connection error.
     *
     * @expectedException \Knit\Exceptions\StoreConnectionFailedException
     */
    public function testConnectionError()
    {
        new Store(
            [
                'username' => 'unknown',
                'password' => 'notsosecret',
                'hostname' => '127.0.1.1',
                'database' => 'nothing'
            ],
            new CriteriaParser()
        );
    }

    /**
     * Tests throwing a proper exception when the config was wrong.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testConfigError()
    {
        new Store(
            [
                'username' => 'unknown',
                'password' => 'notsosecret',
                'hostname' => '127.0.1.1'
            ],
            new CriteriaParser()
        );
    }

    /**
     * Tests the connection and clears the database for preparation for tests.
     */
    public function testConnection()
    {
        $store = $this->repository->getStore();
        $store->getDatabase()
            ->dropCollection($this->repository->getCollection());

        $this->assertInstanceOf(\MongoClient::class, $store->getClient());
    }

    /**
     * Tests inserting data to the store.
     *
     * @depends testConnection
     */
    public function testInsert()
    {
        // make sure the collection is empty first
        $this->assertEmpty($this->repository->find());

        // create some hobbits
        foreach ([
            ['name' => 'Frodo', 'surname' => 'Baggins', 'height' => 140],
            ['name' => 'Sam', 'surname' => 'Gamgee', 'height' => 132],
            ['name' => 'Merry', 'surname' => 'Brandybuck', 'height' => 135],
            ['name' => 'Pippin', 'surname' => 'Took', 'height' => 142]
        ] as $data) {
            $hobbit = $this->repository->createWithData($data);
            $this->repository->save($hobbit);
        }
    }

    /**
     * Tests that invalid add query throws an exception.
     *
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testInsertError()
    {
        // it looks like the best way to test this is an actual unit test with a mock
        $store = $this->provideStore();

        $store->getDatabase()
            ->hobbits
            ->expects($this->once())
            ->method('insert')
            ->will($this->throwException(new \MongoException()));

        $store->add('hobbits', []);
    }

    /**
     * Provides various criteria and params to test when finding objects.
     *
     * @return array
     */
    public function provideFindCriteria()
    {
        return [
            [ // #0
                'criteria' => [],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #1
                'criteria' => ['name' => 'Frodo'],
                'params' => [],
                'expected' => ['Frodo']
            ],
            [ // #2
                'criteria' => ['name:not' => 'Frodo'],
                'params' => ['orderBy' => 'height', 'orderDir' => Knit::ORDER_DESC],
                'expected' => ['Pippin', 'Merry', 'Sam']
            ],
            [ // #3
                'criteria' => ['name:in' => ['Frodo', 'Sam'], 'height:gt' => 135],
                'params' => [],
                'expected' => ['Frodo']
            ],
            [ // #4
                'criteria' => ['height:gte' => 132, 'height:lte' => 140],
                'params' => ['orderBy' => ['height' => Knit::ORDER_DESC, 'name' => Knit::ORDER_DESC]],
                'expected' => ['Frodo', 'Merry', 'Sam']
            ],
            [ // #5
                'criteria' => ['surname' => null],
                'params' => [],
                'expected' => []
            ],
            [ // #6
                'criteria' => ['surname:not' => null],
                'params' => ['start' => 2, 'limit' => 2],
                'expected' => ['Merry', 'Pippin']
            ],
            [ // #7
                'criteria' => ['name:not_in' => ['Sam', 'Merry']],
                'params' => [],
                'expected' => ['Frodo', 'Pippin']
            ],
            [ // #8
                'criteria' => [
                    Knit::LOGIC_OR => ['height:lt' => 142, 'surname' => 'Took']
                ],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #9
                'criteria' => [
                    Knit::LOGIC_OR => [
                        ['name' => 'Frodo', 'surname' => 'Baggins'],
                        ['name' => 'Samwise', 'surname' => 'Gamgee'],
                        ['name' => 'Merry', 'surname' => 'Brandybuck']
                    ],
                    Knit::LOGIC_AND => ['height:gte' => 100, 'height:lte' => 150]
                ],
                'params' => ['orderBy' => 'name'],
                'expected' => ['Frodo', 'Merry']
            ],
            [ // #10
                'criteria' => ['name:not_in' => ['Sam', 'Merry']],
                'params' => [],
                'expected' => ['Frodo', 'Pippin']
            ],
            [ // #11
                'criteria' => ['name' => []],
                'params' => [],
                'expected' => []
            ],
            [ // #12
                'criteria' => ['name:not' => []],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #13
                'criteria' => [],
                'params' => ['orderBy' => ['name', 'height']],
                'expected' => ['Frodo', 'Merry', 'Pippin', 'Sam']
            ],
            [ // #14
                'criteria' => [
                    Knit::LOGIC_OR => ['height:lt' => 142, 'surname:like' => '%oo%']
                ],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #15
                'criteria' => ['name:in' => ['Sam', 'Pippin']],
                'params' => [],
                'expected' => ['Sam', 'Pippin']
            ],
            [ // #16
                'criteria' => ['name:in' => []],
                'params' => [],
                'expected' => []
            ],
            [ // #17
                'criteria' => ['name:not_in' => []],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #18
                'criteria' => ['name:regex' => '/rr/si'],
                'params' => [],
                'expected' => ['Merry']
            ],
            [ // #19
                'criteria' => ['name:like' => '%oo\%'],
                'params' => [],
                'expected' => []
            ],
            [ // #20
                'criteria' => ['name' => 'Frodo', 'name:eq' => 'Sam'],
                'params' => [],
                'expected' => []
            ],
            [ // #21
                'criteria' => [
                    Knit::LOGIC_OR => ['height:lt' => 142, 'surname:like' => '%oo%'],
                    Knit::LOGIC_AND => ['height:gte' => 100, 'name' => 'Frodo']
                ],
                'params' => [],
                'expected' => ['Frodo']
            ],
        ];
    }

    /**
     * Tests finding objects by various queries.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search params.
     * @param array $expected Expected results.
     *
     * @depends      testInsert
     * @dataProvider provideFindCriteria
     */
    public function testFind(array $criteria, array $params, array $expected)
    {
        try {
            $hobbits = $this->repository->find($criteria, $params);
        } catch (\Exception $e) {
            throw new \Exception($this->logger->getLastMessage(), 0, $e);
        }

        $this->assertCount(count($expected), $hobbits, $this->logger->getLastMessage());
        $this->assertEquals($expected, ObjectUtils::pluck($hobbits, 'name'), $this->logger->getLastMessage());
    }

    /**
     * Tests finding by multiple id's.
     *
     * @depends testInsert
     */
    public function testFindByMultipleIds()
    {
        $hobbits = $this->repository->find();
        $otherHobbits = $this->repository->find(
            [
                'id:in' => ObjectUtils::pluck($hobbits, 'id')
            ]
        );

        $this->assertEquals($hobbits, $otherHobbits, $this->logger->getLastMessage());
    }

    /**
     * Tests that an exception is thrown when trying to use an empty ID for lookup.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testFindByEmptyId()
    {
        $this->repository->findOneById('');
    }

    /**
     * Tests that an exception is thrown when trying to use few empty ID's for lookup.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testFindByEmptyIds()
    {
        $this->repository->find(['id:in' => ['']]);
    }

    /**
     * Tests that an exception is thrown when an unrecognized operator is used.
     *
     * @expectedException \Knit\Exceptions\InvalidOperatorException
     */
    public function testFindWithInvalidOperator()
    {
        $this->repository->find(['height:exists' => 142]);
    }

    /**
     * Tests that an exception is thrown when the find query fails.
     *
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testFindQueryError()
    {
        // it looks like the best way to test this is an actual unit test with a mock
        $store = $this->provideStore();

        $store->getDatabase()
            ->hobbits
            ->expects($this->once())
            ->method('find')
            ->will($this->throwException(new \MongoException()));

        $store->find('hobbits');
    }

    /**
     * Tests counting objects by various queries.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search params.
     * @param array $expected Expected results.
     *
     * @depends      testInsert
     * @dataProvider provideFindCriteria
     */
    public function testCount(array $criteria, array $params, array $expected)
    {
        $hobbitsFound = $this->repository->count($criteria, $params);
        $this->assertEquals(count($expected), $hobbitsFound);
    }

    /**
     * Tests that an exception is thrown when the count query fails.
     *
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testCountQueryError()
    {
        // it looks like the best way to test this is an actual unit test with a mock
        $store = $this->provideStore();

        $store->getDatabase()
            ->hobbits
            ->expects($this->once())
            ->method('count')
            ->will($this->throwException(new \MongoException()));

        $store->count('hobbits');
    }

    /**
     * Tests updating objects.
     *
     * @depends testInsert
     */
    public function testUpdate()
    {
        $hobbit = $this->repository->findOneByName('Frodo');
        $this->assertEquals('Baggins', $hobbit->getSurname());

        $hobbit->setSurname('Bagginsses');
        $this->repository->save($hobbit);

        $updatedHobbit = $this->repository->findOneByName('Frodo');
        $this->assertEquals('Bagginsses', $hobbit->getSurname());
    }

    /**
     * Tests that an exception is thrown when the query fails.
     *
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testUpdateError()
    {
        // it looks like the best way to test this is an actual unit test with a mock
        $store = $this->provideStore();

        $store->getDatabase()
            ->hobbits
            ->expects($this->once())
            ->method('update')
            ->will($this->throwException(new \MongoException()));

        $store->update('hobbits');
    }

    /**
     * Tests removing objects from the store.
     *
     * @depends testFind
     */
    public function testRemove()
    {
        $this->assertEquals(4, $this->repository->count());

        $hobbit = $this->repository->findOneByName('Merry');
        $this->repository->delete($hobbit);

        $this->assertEquals(3, $this->repository->count());
    }

    /**
     * Tests that an exception is thrown when the query fails.
     *
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testRemoveError()
    {
        // it looks like the best way to test this is an actual unit test with a mock
        $store = $this->provideStore();

        $store->getDatabase()
            ->hobbits
            ->expects($this->once())
            ->method('remove')
            ->will($this->throwException(new \MongoException()));

        $store->remove('hobbits');
    }

    /**
     * Provides a Store stub for unit tests.
     */
    protected function provideStore()
    {

        $client = $this->getMockBuilder(\MongoClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $database = $this->getMockBuilder(\MongoDB::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collection = $this->getMockBuilder(\MongoCollection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $database->hobbits = $collection;

        $store = new Store(
            [
                'username' => getenv('MONGODB_USER'),
                'password' => getenv('MONGODB_PASSWORD'),
                'hostname' => getenv('MONGODB_HOST'),
                'port' => getenv('MONGODB_PORT'),
                'database' => getenv('MONGODB_DBNAME'),
            ],
            new CriteriaParser(),
            $this->logger
        );

        $this->setPrivateProperty($store, 'client', $client);
        $this->setPrivateProperty($store, 'database', $database);

        return $store;
    }

    /**
     * Sets a value for a private/protected property of the given object through Reflection. Used in mocks.
     *
     * @param object $object       Object on which to set the property.
     * @param string $propertyName Property name.
     * @param mixed  $value        New value.
     */
    protected function setPrivateProperty($object, $propertyName, $value)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
