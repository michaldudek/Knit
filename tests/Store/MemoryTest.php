<?php
namespace Knit\Tests\Store;

use Symfony\Component\EventDispatcher\EventDispatcher;

use MD\Foundation\Utils\ObjectUtils;

use Knit\Tests\Fixtures;

use Knit\Criteria\CriteriaExpression;
use Knit\DataMapper\ArraySerializable\ArraySerializer;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Store\Memory\CriteriaMatcher;
use Knit\Store\Memory\Store;
use Knit\Repository;
use Knit\Knit;

/**
 * Tests Memory store.
 *
 * @package    Knit
 * @subpackage Store
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2016 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class MemoryTest extends \PHPUnit_Framework_TestCase
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
        $this->logger = new Fixtures\TestLogger();

        $store = new Store(
            new CriteriaMatcher(),
            $this->logger
        );

        $this->repository = new Repository(
            Fixtures\Hobbit::class,
            'hobbits',
            $store,
            new ArraySerializer(),
            new EventDispatcher()
        );
    }

    /**
     * Tests inserting data to the store.
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
     * Inserts data to the store.
     */
    private function insertData()
    {
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
                'params' => ['orderBy' => ['height' => Knit::ORDER_DESC]],
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
                'params' => ['orderBy' => ['name']],
                'expected' => ['Frodo', 'Merry', 'Pippin', 'Sam']
            ],
            [ // #15
                'criteria' => [
                    Knit::LOGIC_OR => ['height:lt' => 142, 'surname:like' => '%oo%']
                ],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #16
                'criteria' => ['name:in' => ['Sam', 'Pippin']],
                'params' => [],
                'expected' => ['Sam', 'Pippin']
            ],
            [ // #17
                'criteria' => ['name:in' => []],
                'params' => [],
                'expected' => []
            ],
            [ // #18
                'criteria' => ['name:not_in' => []],
                'params' => [],
                'expected' => ['Frodo', 'Sam', 'Merry', 'Pippin']
            ],
            [ // #19
                'criteria' => ['name:regex' => '/rr/si'],
                'params' => [],
                'expected' => ['Merry']
            ],
            [ // #20
                'criteria' => ['name:like' => '%oo\%'],
                'params' => [],
                'expected' => []
            ],
            [ // #21
                'criteria' => ['name' => 'Frodo', 'name:eq' => 'Sam'],
                'params' => [],
                'expected' => []
            ],
            [ // #22
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
        $this->insertData();

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
        $this->insertData();

        $this->repository->find(['height:exists' => 142]);
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
        $this->insertData();

        $hobbitsFound = $this->repository->count($criteria, $params);
        $this->assertEquals(count($expected), $hobbitsFound);
    }

    /**
     * Tests updating objects.
     *
     * @depends testInsert
     */
    public function testUpdate()
    {
        $this->insertData();

        $hobbit = $this->repository->findOneByName('Frodo');
        $this->assertEquals('Baggins', $hobbit->getSurname());

        $hobbit->setSurname('Bagginsses');
        $this->repository->save($hobbit);

        $updatedHobbit = $this->repository->findOneByName('Frodo');
        $this->assertEquals('Bagginsses', $updatedHobbit->getSurname());
    }

    /**
     * Tests removing objects from the store.
     *
     * @depends testFind
     */
    public function testRemove()
    {
        $this->insertData();

        $this->assertEquals(4, $this->repository->count());

        $hobbit = $this->repository->findOneByName('Merry');
        $this->repository->delete($hobbit);

        $this->assertEquals(3, $this->repository->count());
    }
}
