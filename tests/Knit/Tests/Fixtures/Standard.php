<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;
use Knit\KnitOptions;

class Standard extends AbstractEntity
{

    public static $_collection = 'standards';

    public static function _getStructure() {
        return array(
            'id' => array(),
            'some_property' => array(
                'type' => KnitOptions::TYPE_STRING,
                'default' => 'value'
            )
        );
    }

}