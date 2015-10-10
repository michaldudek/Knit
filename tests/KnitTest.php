<?php
namespace Knit\Tests;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Knit\DataMapper\ArraySerializable\ArraySerializer;
use Knit\Store\StoreInterface;
use Knit\Repository;
use Knit\Knit;

/**
 * Tests Knit class.
 *
 * @package    Knit
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @covers Knit\Knit
 */
class KnitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests getting a repository with default settings.
     */
    public function testGetRepository()
    {
        $mocks = $this->provideMocks();
        $knit = new Knit($mocks['store'], $mocks['dataMapper'], $mocks['eventDispatcher']);

        $objectClass = Fixtures\Hobbit::class;
        $collection = 'hobbits';

        $repository = $knit->getRepository($objectClass, $collection);

        $this->assertInstanceOf(Repository::class, $repository);

        // assert all data have been correctly passed (defaults)
        $this->assertEquals($objectClass, $repository->getObjectClass());
        $this->assertEquals($collection, $repository->getCollection());
        $this->assertSame($mocks['store'], $repository->getStore());
        $this->assertSame($mocks['dataMapper'], $repository->getDataMapper());
        $this->assertSame($mocks['eventDispatcher'], $repository->getEventDispatcher());

        // assert that for second time we will get the same instance
        // (even if class name is prefix with namespace separator)
        $this->assertSame($repository, $knit->getRepository('\\'. $objectClass, $collection));
    }

    /**
     * Tests getting a repository with custom store and data mapper.
     */
    public function testGetRepositoryWithCustomSettings()
    {
        $defaultMocks = $this->provideMocks();
        $knit = new Knit($defaultMocks['store'], $defaultMocks['dataMapper'], $defaultMocks['eventDispatcher']);

        $mocks = $this->provideMocks();

        $objectClass = Fixtures\Hobbit::class;
        $collection = 'hobbits';

        $repository = $knit->getRepository($objectClass, $collection, $mocks['store'], $mocks['dataMapper']);

        $this->assertNotSame($defaultMocks['store'], $repository->getStore());
        $this->assertNotSame($defaultMocks['dataMapper'], $repository->getDataMapper());
        $this->assertSame($mocks['store'], $repository->getStore());
        $this->assertSame($mocks['dataMapper'], $repository->getDataMapper());
    }

    /**
     * Tests getting a repository of an auto resolved class.
     */
    public function testGetRepositoryWithAutoResolvedClass()
    {
        $mocks = $this->provideMocks();
        $knit = new Knit($mocks['store'], $mocks['dataMapper'], $mocks['eventDispatcher']);

        $objectClass = Fixtures\Elf::class;
        $collection = 'elves';

        $repository = $knit->getRepository($objectClass, $collection);

        $this->assertInstanceOf(Fixtures\ElfRepository::class, $repository);
    }

    /**
     * Tests getting a repository of a custom defined class.
     */
    public function testGetRepositoryWithCustomClass()
    {
        $mocks = $this->provideMocks();
        $knit = new Knit($mocks['store'], $mocks['dataMapper'], $mocks['eventDispatcher']);

        $objectClass = Fixtures\ElvenGift::class;
        $collection = 'gifts';

        $repository = $knit->getRepository($objectClass, $collection, null, null, Fixtures\ElvenGiftsStash::class);

        $this->assertInstanceOf(Fixtures\ElvenGiftsStash::class, $repository);
    }

    /**
     * Tests getting a repository when the passed repository class is invalid.
     *
     * @expectedException \RuntimeException
     */
    public function testGetRepositoryWithCustomInvalidClass()
    {
        $mocks = $this->provideMocks();
        $knit = new Knit($mocks['store'], $mocks['dataMapper'], $mocks['eventDispatcher']);

        $objectClass = Fixtures\Orc::class;
        $collection = 'orcs';

        $knit->getRepository($objectClass, $collection, null, null, Fixtures\Mordor::class);
    }

    /**
     * Provides mock objects.
     *
     * @return array
     */
    protected function provideMocks()
    {
        $mocks = [];

        $mocks['store'] = $this->getMockBuilder(StoreInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mocks['dataMapper'] = new ArraySerializer();

        $mocks['eventDispatcher'] = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $mocks;
    }
}
