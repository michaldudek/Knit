<?php
namespace Knit\Tests\Validators;

use Knit\Validators\ValidatorInterface;
use Knit\Validators\MinLengthValidator;

class MinLengthValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideValidationData
     */
    public function testValidator($val, $against, $valid) {
        $validator = new MinLengthValidator();
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
            array('123123', 3, true),
            array('123123', 10, false),
            array(123123, 10, false),
        );
    }

}