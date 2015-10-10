<?php
namespace Knit\Tests\Store;

use Symfony\Component\EventDispatcher\EventDispatcher;

use MD\Foundation\Utils\ObjectUtils;

use Knit\Tests\Fixtures;

use Knit\Criteria\CriteriaExpression;
use Knit\DataMapper\ArraySerializable\ArraySerializer;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Store\DoctrineDBAL\CriteriaParser;
use Knit\Store\DoctrineDBAL\Store;
use Knit\Repository;
use Knit\Knit;

/**
 * Tests Knit class.
 *
 * @package    Knit
 * @subpackage Store
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class DoctrineDBALTest extends \PHPUnit_Framework_TestCase
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
        // DB=mysql in travis
        // MYSQL=1 in the test Vagrant VM
        if (getenv('DB') !== 'mysql' && getenv('MYSQL') != '1') {
            $this->markTestSkipped('Not testing MySQL.');
        }

        try {
            $this->logger = new Fixtures\TestLogger();

            $store = new Store([
                'driver' => 'pdo_mysql',
                'user' => getenv('MYSQL_USER'),
                'password' => getenv('MYSQL_PASSWORD'),
                'host' => getenv('MYSQL_HOST'),
                'port' => getenv('MYSQL_PORT'),
                'dbname' => getenv('MYSQL_DBNAME')
            ], new CriteriaParser(), $this->logger);

            $this->repository = new Repository(
                Fixtures\Hobbit::class,
                'hobbits',
                $store,
                new ArraySerializer(),
                new EventDispatcher()
            );

        } catch (StoreConnectionFailedException $e) {
            $this->markTestSkipped('Could not connect to MySQL: '. $e->getMessage());
        }
    }

    /**
     * Tests throwing a proper exception when there was a connection error.
     *
     * @expectedException \Knit\Exceptions\StoreConnectionFailedException
     */
    public function testConnectionError()
    {
        new Store([
            'driver' => 'pdo_mysql',
            'user' => 'unknown',
            'password' => 'notsosecret',
            'host' => '127.0.1.1'
        ], new CriteriaParser());
    }

    /**
     * Tests the connection and also prepares the schema for tests.
     */
    public function testConnection()
    {
        $connection = $this->repository->getStore()->getConnection();

        $sql = file_get_contents(__DIR__ .'/../../resources/tests/knit_test.sql');
        $connection->query($sql);
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
        $this->repository->getStore()->add(
            $this->repository->getCollection(),
            ['no_such_column' => 1]
        );
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
                'criteria' => ['name' => ['Frodo', 'Sam'], 'height:gt' => 135],
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
                    Knit::LOGIC_OR => ['height:lt' => 142, 'surname:like' => '%oo%']
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
                'criteria' => ['name:not' => ['Sam', 'Merry']],
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
                'criteria' => ['name:not_like' => '%r%'],
                'params' => [],
                'expected' => ['Sam', 'Pippin']
            ],
            [ // #14
                'criteria' => [],
                'params' => ['orderBy' => ['name', 'height']],
                'expected' => ['Frodo', 'Merry', 'Pippin', 'Sam']
            ],
        ];
    }

    /**
     * Tests finding objects by various queries.
     *
     * @depends testInsert
     * @dataProvider provideFindCriteria
     */
    public function testFind(array $criteria, array $params, array $expected)
    {
        $hobbits = $this->repository->find($criteria, $params);

        $this->assertCount(count($expected), $hobbits, $this->logger->getLastMessage());
        $this->assertEquals($expected, ObjectUtils::pluck($hobbits, 'name'), $this->logger->getLastMessage());
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
        $this->repository->find([], ['orderBy' => 'no_such_column']);
    }

    /**
     * Tests counting objects by various queries.
     *
     * @depends testInsert
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
        $this->repository->count(['no_such_column' => 5]);
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
     * @depends testInsert
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testUpdateError()
    {
        $this->repository->getStore()->update(
            $this->repository->getCollection(),
            null,
            ['no_such_column' => 1]
        );
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
     * @depends testInsert
     * @expectedException \Knit\Exceptions\StoreQueryErrorException
     */
    public function testRemoveError()
    {
        $this->repository->getStore()->remove(
            $this->repository->getCollection(),
            new CriteriaExpression(['no_such_column' => 45])
        );
    }
}
