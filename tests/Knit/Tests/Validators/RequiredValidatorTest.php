<?php
namespace Knit\Tests\Validators;

use Knit\Validators\ValidatorInterface;
use Knit\Validators\RequiredValidator;

class RequiredValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideValidationData
     */
    public function testValidator($val, $against, $valid) {
        $validator = new RequiredValidator();
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
            array(null, true, false),
            array(null, false, true),
            array(true, true, true),
            array(true, false, true),
            array('    ', true, false),
            array('    ', false, true),
            array('', true, false),
            array('', false, true),
            array('    sdf', true, true),
            array('    sdf', false, true),
            array(0, true, true),
            array(0, false, true),
            array('0', true, true),
            array('0', false, true)
        );
    }

}