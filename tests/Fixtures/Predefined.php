<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;

class Predefined extends AbstractEntity
{

    public static $_collection = 'definitions';

    public static function _getStructure() {
        return array(
            'id' => array()
        );
    }

}