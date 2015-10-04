<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;

class NoStructureEntity extends AbstractEntity
{

    public static $_collection = 'undefined';

    public static function _getStructure() {
        return array();
    }

}