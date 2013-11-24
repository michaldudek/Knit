<?php
namespace Knit\Tests\Extensions;

use Knit\Tests\Fixtures\Standard;

use Knit\Entity\Repository;
use Knit\Extensions\GlobalFilter;
use Knit\Events\WillAddEntity;
use Knit\Events\WillDeleteOnCriteria;
use Knit\Events\WillReadFromStore;
use Knit\KnitOptions;

/**
 * @coversDefaultClass Knit\Extensions\GlobalFilter
 */
class GlobalFilterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::addFilterToCriteriaOnRead
     */
    public function testAddFilterToCriteriaOnRead() {
        $extension = new GlobalFilter(array(
            'account_id' => 5
        ));

        $event = new WillReadFromStore(array(
            'comments:gt' => 50
        ));

        $extension->addFilterToCriteriaOnRead($event);

        $criteria = $event->getCriteria();
        $this->assertArrayHasKey('account_id', $criteria);
        $this->assertEquals(5, $criteria['account_id']);   
    }

    /**
     * @covers ::addFilterToCriteriaOnRead
     */
    public function testAddComplexFilterCriteriaOnRead() {
        $extension = new GlobalFilter(array(
            'account_id' => 5,
            KnitOptions::LOGIC_OR => array(
                'postdate:gt' => strtotime('1 year ago'),
                'sticky' => true
            )
        ));

        $event = new WillReadFromStore(array(
            'comments:gt' => 50,
            KnitOptions::LOGIC_OR => array(
                'likes:gt' => 1000,
                'retweets:gt' => 1000
            )
        ));

        $extension->addFilterToCriteriaOnRead($event);

        $criteria = $event->getCriteria();

        $this->assertEquals(array(
            'account_id' => 5,
            'comments:gt' => 50,
            KnitOptions::LOGIC_OR => array(
                'postdate:gt' => strtotime('1 year ago'),
                'sticky' => true,
                'likes:gt' => 1000,
                'retweets:gt' => 1000
            )
        ), $criteria);
    }

    /**
     * @covers ::addFilterToCriteriaOnDelete
     */
    public function testAddFilterToCriteriaOnDelete() {
        $extension = new GlobalFilter(array(
            'account_id' => 5
        ));

        $event = new WillDeleteOnCriteria(array(
            'comments:gt' => 50
        ));

        $extension->addFilterToCriteriaOnDelete($event);

        $criteria = $event->getCriteria();
        $this->assertArrayHasKey('account_id', $criteria);
        $this->assertEquals(5, $criteria['account_id']);   
    }

    /**
     * @covers ::addFilterToCriteriaOnDelete
     */
    public function testAddComplexFilterCriteriaOnDelete() {
        $extension = new GlobalFilter(array(
            'account_id' => 5,
            KnitOptions::LOGIC_OR => array(
                'postdate:gt' => strtotime('1 year ago'),
                'sticky' => true
            )
        ));

        $event = new WillDeleteOnCriteria(array(
            'comments:gt' => 50,
            KnitOptions::LOGIC_OR => array(
                'likes:gt' => 1000,
                'retweets:gt' => 1000
            )
        ));

        $extension->addFilterToCriteriaOnDelete($event);

        $criteria = $event->getCriteria();

        $this->assertEquals(array(
            'account_id' => 5,
            'comments:gt' => 50,
            KnitOptions::LOGIC_OR => array(
                'postdate:gt' => strtotime('1 year ago'),
                'sticky' => true,
                'likes:gt' => 1000,
                'retweets:gt' => 1000
            )
        ), $criteria);
    }

    /**
     * @covers ::setFilterEntityPropertiesOnAdd
     */
    public function testSettingFilterEntityPropertiesOnAdd() {
        $time = time();

        $extension = new GlobalFilter(array(), array(
            'updated_at' => $time,
            'updated_by' => 5
        ));

        $entity = new Standard();
        $repository = $this->provideRepository(Standard::__class());
        $entity->_setRepository($repository);
        $event = new WillAddEntity($entity);

        $extension->setFilterEntityPropertiesOnAdd($event);

        $this->assertEquals($time, $entity->getUpdatedAt());
        $this->assertEquals(5, $entity->getUpdatedBy());
    }

    /**
     * @covers ::addExtension
     */
    public function testAddingExtension() {
        $extension = $this->getMock('Knit\Extensions\GlobalFilter', array(
            'addFilterToCriteriaOnRead',
            'addFilterToCriteriaOnDelete',
            'setFilterEntityPropertiesOnAdd'
        ), array(array(
            'some_criteria' => 'rediculous'
        ), array()));
        $extension->expects($this->once())
            ->method('addFilterToCriteriaOnRead')
            ->with($this->anything());
        $extension->expects($this->once())
            ->method('addFilterToCriteriaOnDelete')
            ->with($this->anything());
        $extension->expects($this->once())
            ->method('setFilterEntityPropertiesOnAdd')
            ->with($this->anything());

        $mocks = $this->provideMocks();
        $mocks['store']->expects($this->any())
            ->method('find')
            ->will($this->returnValue(array()));
        $repository = $this->provideRepository(Standard::__class(), $mocks);
        $repository->addExtension($extension);

        // should call addFilterToCriteriaOnRead
        $repository->find(array(
            'stuff' => true
        ));

        // should call addFilterToCriteriaOnDelete
        $repository->deleteOnCriteria(array(
            'archived' => 1
        )); 

        // should call setFilterEntityPropertiesOnAdd
        $entity = new Standard();
        $entity->_setRepository($repository);
        try {
            $repository->save($entity);
        } catch(\RuntimeException $e) {
            // will most likely trigger this exception since there's no connection with the store
        }
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