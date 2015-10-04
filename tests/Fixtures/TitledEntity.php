<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;
use Knit\KnitOptions;

class TitledEntity extends AbstractEntity
{

    public static $_collection = 'titles';

    public static function _getStructure() {
        return array(
            'id' => array(),
            'title' => array(
                'type' => KnitOptions::TYPE_STRING,
                'maxLength' => 72,
                'required' => true
            )
        );
    }

}