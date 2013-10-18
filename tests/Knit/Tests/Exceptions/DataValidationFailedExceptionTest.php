<?php
namespace Knit\Tests\Exceptions;

use InvalidArgumentException;

use Knit\Exceptions\DataValidationFailedException;
use Knit\Exceptions\PropertyValidationFailedException;

class DataValidationFailedExceptionTest extends \PHPUnit_Framework_TestCase
{

    public function testException() {
        $errors = array();
        for($i = 0; $i < 5; $i++) {
            $errors[] = $this->getMockBuilder('Knit\Exceptions\PropertyValidationFailedException')
                ->disableOriginalConstructor()
                ->getMock();
        }

        $exception = new DataValidationFailedException(
            'Person',
            $errors
        );

        $this->assertTrue($exception instanceof InvalidArgumentException);

        $this->assertEquals('Person', $exception->getEntityClass());
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertContains('Person', $exception->getMessage());
    }

}