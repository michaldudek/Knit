<?php
namespace Knit\Tests\Entity;

use Knit\Entity\AbstractEntity;

class AbstractEntityTest extends \PHPUnit_Framework_TestCase
{

    public function testEntity() {
        $entity = $this->getMockForAbstractClass(AbstractEntity::__class());

        $this->assertInternalType('array', AbstractEntity::_getStructure());
    }

}