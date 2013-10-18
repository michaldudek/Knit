<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;

class Standard extends AbstractEntity
{

    public static $_collection = 'standards';

    public static function _getStructure() {
        return array(
            'id' => array(),
            'some_property' => array(
                'default' => 'value'
            )
        );
    }

}