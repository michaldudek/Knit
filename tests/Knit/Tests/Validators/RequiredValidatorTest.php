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
            array(null, null, false),
            array(true, null, true),
            array('    ', null, false),
            array('', null, false),
            array('    sdf', null, true),
            array(0, null, true),
            array('0', null, true)
        );
    }

}