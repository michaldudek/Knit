<?php
namespace Knit\Tests\Entity;

use Knit\Tests\Fixtures\Dummy;
use Knit\Tests\Fixtures\ExtendedEntity;
use Knit\Tests\Fixtures\Standard;
use Knit\Tests\Fixtures\ValidatingEntity;

use Knit\Entity\Repository;

/**
 * @coversDefaultClass \Knit\Entity\Repository
 */
class RepositoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::__construct
     */
    public function testConstructingRepository() {
        $mocks = $this->provideMocks();
        $mocks['store']->expects($this->once())
            ->method('didBindToRepository')
            ->with($this->callback(function($argument) {
                return $argument instanceof Repository;
            }));

        $repository = new Repository(Standard::__class(), $mocks['store'], $mocks['knit']);
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::__construct
     */
    public function testConstructingRepositoryForInvalidEntity() {
        $mocks = $this->provideMocks();
        $repository = new Repository(Dummy::__class(), $mocks['store'], $mocks['knit']);
    }

    /**
     * @covers ::addExtension
     */
    public function testAddingExtensions() {
        $repository = $this->provideRepository();

        $extension = $this->getMock('Knit\Extensions\ExtensionInterface');
        $extension->expects($this->once())
            ->method('addExtension')
            ->with($this->identicalTo($repository));

        $repository->addExtension($extension);
    }

    /**
     * @covers ::addExtension
     */
    public function testAddingExtensionsFromEntityConfiguration() {
        $mocks = $this->provideMocks();
        $timestampExtension = $this->getMock('Knit\Extensions\ExtensionInterface');
        $timestampExtension->expects($this->once())
            ->method('addExtension');
            //->with($this->anything());
        $softdeleteExtension = $this->getMock('Knit\Extensions\ExtensionInterface');
        $softdeleteExtension->expects($this->once())
            ->method('addExtension');

        $mocks['knit']->expects($this->any())
            ->method('getExtension')
            ->will($this->returnCallback(function($name) use ($timestampExtension, $softdeleteExtension) {
                if ($name === 'softdelete') {
                    return $softdeleteExtension;
                } else if ($name === 'timestamp') {
                    return $timestampExtension;
                }

                return null;
            }));

        $repository = new Repository(ExtendedEntity::__class(), $mocks['store'], $mocks['knit']);
    }

    protected function provideMocks() {
        $mocks = array(
            'store' => $this->getMock('Knit\Store\StoreInterface'),
            'knit' => $this->getMockBuilder('Knit\Knit')
                ->disableOriginalConstructor()
                ->getMock()
        );
        return $mocks;
    }

    protected function provideRepository($entityClass = '\Knit\Tests\Fixtures\Standard', array $mocks = array()) {
        $mocks = (!empty($mocks)) ? $mocks : $this->provideMocks();
        return new Repository($entityClass, $mocks['store'], $mocks['knit']);
    }

}