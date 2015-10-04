<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;

class NotExtending extends AbstractEntity
{

    public static $_collection = 'autos';

    public static function _getStructure() {
        return array(
            'id' => array()
        );
    }

}