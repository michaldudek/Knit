<?php
namespace Knit\Tests\Validators;

use Knit\Validators\ValidatorInterface;
use Knit\Validators\EqualsValidator;

class EqualsValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideValidationData
     */
    public function testValidator($val, $against, $valid) {
        $validator = new EqualsValidator();
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
            array(123, 123, true),
            array(123, '123', false),
            array('lorem ipsum', 'dolot sit', false),
            array('lorem ipsum', 'lorem ipsum', true)
        );
    }

}