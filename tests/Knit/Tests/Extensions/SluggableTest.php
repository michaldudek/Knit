<?php
namespace Knit\Tests\Extensions;

use Knit\Tests\Fixtures\NamedEntity;
use Knit\Tests\Fixtures\Standard;
use Knit\Tests\Fixtures\TitledEntity;

use Knit\Entity\Repository;
use Knit\Extensions\Sluggable;
use Knit\Events\WillAddEntity;
use Knit\KnitOptions;
use Knit\Knit;

class SluggableTest extends \PHPUnit_Framework_TestCase
{

    public function testAddingExtension() {
        $extension = $this->getMock('Knit\Extensions\Sluggable', array(
            'setSlugOnAdd'
        ));
        $extension->expects($this->once())
            ->method('setSlugOnAdd')
            ->with($this->anything());
        
        $mocks = $this->provideMocks();
        $mocks['knit'] = new Knit($this->getMock('Knit\Store\StoreInterface'));

        $repository = $this->provideRepository(Standard::__class(), $mocks);

        $repository->addExtension($extension);

        // check if the structure has been extended
        $structure = $repository->getEntityStructure();
        $this->assertArrayHasKey('slug', $structure);
        $this->assertEquals(array(
            'type' => KnitOptions::TYPE_STRING,
                'maxLength' => 50,
                'required' => false,
                'default' => ''
        ), $structure['slug']);

        // provide test entity
        $entity = $repository->createWithData(array());

        // should call setSlugOnAdd
        $repository->save($entity);

        // should not call setSlugOnAdd
        $entity->setId(123); // will trigger update instead of insert
        $entity->setSomeProperty('lipsum');
        $repository->save($entity);
    }

    public function testSettingSlugFromTitle() {
        $mocks = $this->provideMocks();
        $mocks['store'] = $this->getMock('Knit\Store\StoreInterface');
        $mocks['store']->expects($this->any())
            ->method('count')
            ->will($this->returnValue(0));
        $mocks['knit'] = new Knit($mocks['store']);

        $repository = $this->provideRepository(TitledEntity::__class(), $mocks);

        $extension = new Sluggable();
        $entity = $repository->createWithData(array(
            'title' => 'Lorem ipsum dolor sit amet'
        ));

        $event = new WillAddEntity($entity);
        $extension->setSlugOnAdd($event);

        $this->assertEquals('lorem-ipsum-dolor-sit-amet', $entity->getSlug());
    }

    public function testSettingSlugFromName() {
        $mocks = $this->provideMocks();
        $mocks['store'] = $this->getMock('Knit\Store\StoreInterface');
        $mocks['store']->expects($this->any())
            ->method('count')
            ->will($this->returnValue(0));
        $mocks['knit'] = new Knit($mocks['store']);

        $repository = $this->provideRepository(NamedEntity::__class(), $mocks);

        $extension = new Sluggable();
        $entity = $repository->createWithData(array(
            'name' => 'Lipsum.com - Generate your Lorem ipsum'
        ));

        $event = new WillAddEntity($entity);
        $extension->setSlugOnAdd($event);

        $this->assertEquals('lipsum-com-generate-your-lorem-ipsum', $entity->getSlug());
    }

    public function testSettingSlugWithAppendix() {
        $mocks = $this->provideMocks();
        $mocks['store'] = $this->getMock('Knit\Store\StoreInterface');
        $mocks['store']->expects($this->any())
            ->method('count')
            ->will($this->onConsecutiveCalls(1, 1, 1, 1, 1));
        $mocks['knit'] = new Knit($mocks['store']);

        $repository = $this->provideRepository(NamedEntity::__class(), $mocks);

        $extension = new Sluggable();
        $entity = $repository->createWithData(array(
            'name' => 'Lipsum.com - Generate your Lorem ipsum'
        ));

        $event = new WillAddEntity($entity);
        $extension->setSlugOnAdd($event);

        $this->assertEquals('lipsum-com-generate-your-lorem-ipsum-5', $entity->getSlug());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFailingToSetSlug() {
        $mocks = $this->provideMocks();
        $mocks['store'] = $this->getMock('Knit\Store\StoreInterface');
        $mocks['store']->expects($this->any())
            ->method('count')
            ->will($this->returnValue(0));
        $mocks['knit'] = new Knit($mocks['store']);

        $repository = $this->provideRepository(Standard::__class(), $mocks);

        $extension = new Sluggable();
        $entity = $repository->createWithData(array(
            'name' => 'Lipsum.com - Generate your Lorem ipsum'
        ));

        $event = new WillAddEntity($entity);
        $extension->setSlugOnAdd($event);
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