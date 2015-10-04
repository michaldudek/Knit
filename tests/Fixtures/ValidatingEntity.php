<?php
namespace Knit\Tests\Fixtures;

use Knit\Entity\AbstractEntity;
use Knit\KnitOptions;

class ValidatingEntity extends AbstractEntity
{

    public static $_collection = 'validations';

    public static function _getStructure() {
        return array(
            'id' => array(
                'type' => KnitOptions::TYPE_INT,
                'unique' => true
            ),
            'slug' => array(
                'type' => KnitOptions::TYPE_STRING,
                'maxLength' => 16,
                'minLength' => 3,
                'unique' => true,
                'validators' => array(
                    'urlFriendly'
                ),
                'required' => true
            ),
            'time_created' => array(
                'type' => KnitOptions::TYPE_INT,
                'readOnly' => true,
                'min' => 1000000,
                'max' => 9999999999
            )
        );
    }

}