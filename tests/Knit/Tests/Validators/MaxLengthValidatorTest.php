<?php
namespace Knit\Tests\Validators;

use Knit\Validators\ValidatorInterface;
use Knit\Validators\MaxLengthValidator;

class MaxLengthValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideValidationData
     */
    public function testValidator($val, $against, $valid) {
        $validator = new MaxLengthValidator();
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
            array('123', 12, true),
            array('123123', 3, false),
            array(123123, 3, false),
            array('lorem ipsum dolor sit amet', 10, false),
            array('lorem ipsum dolor sit amet', 255, true)
        );
    }

}