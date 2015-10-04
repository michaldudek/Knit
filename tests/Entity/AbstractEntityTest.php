<?php
namespace Knit\Tests\Entity;

use Knit\Entity\AbstractEntity;

/**
 * @coversDefaultClass \Knit\Entity\AbstractEntity
 */
class AbstractEntityTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::_getStructure
     * @covers ::_getExtensions
     */
    public function testEntity() {
        $entity = $this->getMockForAbstractClass(AbstractEntity::__class());

        $this->assertInternalType('array', AbstractEntity::_getStructure());
        $this->assertInternalType('array', AbstractEntity::_getExtensions());
    }

}