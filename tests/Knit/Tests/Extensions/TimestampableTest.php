<?php
namespace Knit\Tests\Extensions;

use Knit\Tests\Fixtures\Standard;

use Knit\Entity\Repository;
use Knit\Extensions\Timestampable;
use Knit\Events\WillAddEntity;
use Knit\Events\WillUpdateEntity;
use Knit\KnitOptions;
use Knit\Knit;

class TimestampableTest extends \PHPUnit_Framework_TestCase
{

    public function testAddingExtension() {
        $extension = $this->getMock('Knit\Extensions\Timestampable', array(
            'setCreatedAtOnAdd',
            'setUpdatedAtOnUpdate'
        ));
        $extension->expects($this->once())
            ->method('setCreatedAtOnAdd')
            ->with($this->anything());
        $extension->expects($this->once())
            ->method('setUpdatedAtOnUpdate')
            ->with($this->anything());

        $mocks = $this->provideMocks();
        $mocks['knit'] = new Knit($this->getMock('Knit\Store\StoreInterface'));

        $repository = $this->provideRepository(Standard::__class(), $mocks);

        $repository->addExtension($extension);

        // check if the structure has been extended
        $structure = $repository->getEntityStructure();
        $this->assertArrayHasKey('created_at', $structure);
        $this->assertEquals(array(
            'type' => KnitOptions::TYPE_INT,
            'maxLength' => 10,
            'required' => false,
            'default' => 0
        ), $structure['created_at']);
        $this->assertArrayHasKey('updated_at', $structure);
        $this->assertEquals(array(
            'type' => KnitOptions::TYPE_INT,
            'maxLength' => 10,
            'required' => false,
            'default' => 0
        ), $structure['created_at']);

        // provide test entity
        $entity = $repository->createWithData(array());

        // should call setCreatedAtOnAdd
        $repository->save($entity);

        // should call setUpdatedAtOnUpdate
        $entity->setId(123); // will trigger update instead of insert
        $entity->setSomeProperty('lipsum');
        $repository->save($entity);
    }

    public function testSettingPropertiesAtCreation() {
        $mocks = $this->provideMocks();
        $mocks['knit'] = new Knit($this->getMock('Knit\Store\StoreInterface'));

        $repository = $this->provideRepository(Standard::__class(), $mocks);

        $extension = new Timestampable();
        $entity = $repository->createWithData(array());

        $event = new WillAddEntity($entity);
        $extension->setCreatedAtOnAdd($event);

        // might have jumped to next second so allow 1s lean
        $time = time();
        $this->assertTrue($entity->getCreatedAt() + 1 >= $time);
        $this->assertTrue($entity->getCreatedAt() <= $time + 1);
        $this->assertTrue($entity->getUpdatedAt() + 1 >= $time);
        $this->assertTrue($entity->getUpdatedAt() <= $time + 1);
    }

    public function testSettingPropertiesAtUpdate() {
        $mocks = $this->provideMocks();
        $mocks['knit'] = new Knit($this->getMock('Knit\Store\StoreInterface'));

        $repository = $this->provideRepository(Standard::__class(), $mocks);

        $extension = new Timestampable();
        $entity = $repository->createWithData(array());
        $createdAt = strtotime('1 month ago');
        $entity->setCreatedAt($createdAt);

        $event = new WillUpdateEntity($entity);
        $extension->setUpdatedAtOnUpdate($event);

        // might have jumped to next second so allow 1s lean
        $time = time();
        $this->assertTrue($entity->getUpdatedAt() + 1 >= $time);
        $this->assertTrue($entity->getUpdatedAt() <= $time + 1);

        // make sure that createdAt stayed the same
        $this->assertEquals($createdAt, $entity->getCreatedAt());
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