<?php
namespace Knit\Tests\Validators;

use Knit\Validators\ValidatorInterface;
use Knit\Validators\TypeValidator;
use Knit\KnitOptions;

class TypeValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideValidationData
     */
    public function testValidator($val, $against, $valid) {
        $validator = new TypeValidator();
        $this->assertTrue($validator instanceof ValidatorInterface);

        $validated = $validator->validate($val, $against);

        if ($valid) {
            $this->assertTrue($validated);
        } else {
            $this->assertFalse($validated);
        }
    }

    public function provideValidationData() {
        return array(
            array(123, KnitOptions::TYPE_INT, true),
            array('123', KnitOptions::TYPE_INT, true),
            array('123a', KnitOptions::TYPE_INT, false),
            array(-123, KnitOptions::TYPE_INT, true),
            array('123', KnitOptions::TYPE_STRING, true),
            array(123, KnitOptions::TYPE_STRING, false),
            array('null', KnitOptions::TYPE_STRING, true),
            array(null, KnitOptions::TYPE_STRING, false),
            array(1.23, KnitOptions::TYPE_FLOAT, true)
            //const TYPE_FLOAT = 'float';
            //const TYPE_ENUM = 'enum';
        );
    }

}