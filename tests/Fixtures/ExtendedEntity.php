<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;
use Knit\KnitOptions;

class ExtendedEntity extends AbstractEntity
{

    public static $_collection = 'extensions';

    public static function _getStructure() {
        return array(
            'id' => array(),
            'title' => array(
                'type' => KnitOptions::TYPE_STRING,
                'maxLength' => 52,
                'default' => 'Lorem ipsum'
            )
        );
    }

    public static function _getExtensions() {
        return array(
            'timestamp',
            'softdelete'
        );
    }

}