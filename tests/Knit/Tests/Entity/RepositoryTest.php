<?php
namespace Knit\Tests\Entity;

use Knit\Tests\Fixtures\Dummy;
use Knit\Tests\Fixtures\Standard;
use Knit\Tests\Fixtures\ValidatingEntity;

use Knit\Entity\Repository;

class RepositoryTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructingRepository() {
        $storeMock = $this->getMock('Knit\Store\StoreInterface');
        $storeMock->expects($this->once())
            ->method('didBindToRepository')
            ->with($this->callback(function($argument) {
                return $argument instanceof Repository;
            }));
        $knitMock = $this->getMockBuilder('Knit\Knit')
            ->disableOriginalConstructor()
            ->getMock();

        $repository = new Repository(Standard::__class(), $storeMock, $knitMock);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testConstructingRepositoryForInvalidEntity() {
        $storeMock = $this->getMock('Knit\Store\StoreInterface');
        $knitMock = $this->getMockBuilder('Knit\Knit')
            ->disableOriginalConstructor()
            ->getMock();

        $repository = new Repository(Dummy::__class(), $storeMock, $knitMock);
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