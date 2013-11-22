<?php
namespace Knit\Tests\Entity\Repository;

use Knit\Tests\Fixtures\NoStructureEntity;
use Knit\Tests\Fixtures\Standard;

use Knit\Entity\Repository;
use Knit\KnitOptions;

class StructureTest extends \PHPUnit_Framework_TestCase
{

    public function testGettingStructure() {
        $mocks = $this->provideMocks();
        $mocks['store']->expects($this->any())
            ->method('structure')
            ->will($this->returnValue(array(
                'id' => array(
                    'type' => KnitOptions::TYPE_INT,
                    'maxLength' => 5,
                    'required' => true
                ),
                'diff_property' => array(
                    'type' => KnitOptions::TYPE_STRING,
                    'maxLength' => 255,
                    'required' => false
                )
            )));

        $repository = $this->provideRepository('\Knit\Tests\Fixtures\Standard', $mocks);

        $structure = $repository->getEntityStructure();
        
        $this->assertArrayHasKey('id', $structure);
        $this->assertEquals(array(
            'type' => KnitOptions::TYPE_INT,
            'maxLength' => 5,
            'required' => true
        ), $structure['id']);
        $this->assertArrayHasKey('some_property', $structure);
        $this->assertEquals(array(
            'default' => 'value'
        ), $structure['some_property']);
        $this->assertArrayHasKey('diff_property', $structure);
        $this->assertEquals(array(
            'type' => KnitOptions::TYPE_STRING,
            'maxLength' => 255,
            'required' => false
        ), $structure['diff_property']);
    }

    public function testExtendingStructure() {
        $mocks = $this->provideMocks();
        $mocks['store']->expects($this->any())
            ->method('structure')
            ->will($this->returnValue(array(
                'id' => array(
                    'type' => KnitOptions::TYPE_INT,
                    'maxLength' => 5,
                    'required' => true
                ),
                'diff_property' => array(
                    'type' => KnitOptions::TYPE_STRING,
                    'maxLength' => 255,
                    'required' => false
                )
            )));

        $repository = $this->provideRepository('\Knit\Tests\Fixtures\Standard', $mocks);

        $repository->extendEntityStructure(array(
            'id' => array(
                'maxLength' => 10
            ),
            'diff_property' => array(
                'required' => true
            ),
            'created_at' => array(
                'type' => KnitOptions::TYPE_INT,
                'default' => time()
            )
        ));

        $structure = $repository->getEntityStructure();

        $this->assertArrayHasKey('id', $structure);
        $this->assertEquals(10, $structure['id']['maxLength']);
        $this->assertTrue($structure['id']['required']);
        $this->assertArrayHasKey('diff_property', $structure);
        $this->assertTrue($structure['diff_property']['required']);
        $this->assertArrayHasKey('created_at', $structure);
        $this->assertEquals(KnitOptions::TYPE_INT, $structure['created_at']['type']);
    }

    /**
     * @expectedException \Knit\Exceptions\StructureNotDefinedException
     */
    public function testGettingEmptyStructure() {
        $repository = $this->provideRepository(NoStructureEntity::__class());
        $structure = $repository->getEntityStructure();
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