<?php
namespace Knit\Tests\Exceptions;

use InvalidArgumentException;

use Knit\Exceptions\PropertyValidationFailedException;

class PropertyValidationFailedExceptionTest extends \PHPUnit_Framework_TestCase
{

    public function testException() {
        $exception = new PropertyValidationFailedException(
            'Person',
            'name',
            '123',
            array(
                'notNumeric',
                'regexp',
                'minLength'
            )
        );

        $this->assertTrue($exception instanceof InvalidArgumentException);

        $this->assertEquals('Person', $exception->getEntityClass());
        $this->assertEquals('name', $exception->getProperty());
        $this->assertEquals('123', $exception->getValue());
        $this->assertInternalType('array', $exception->getFailedValidators());
        $this->assertNotEmpty($exception->getFailedValidators());
        $this->assertContains('regexp', $exception->getFailedValidators());
        $this->assertContains('notNumeric', $exception->getFailedValidators());
        $this->assertContains('minLength', $exception->getFailedValidators());
        $this->assertContains('Person', $exception->getMessage());
        $this->assertContains('name', $exception->getMessage());
        $this->assertContains('123', $exception->getMessage());
        $this->assertContains('regexp', $exception->getMessage());
        $this->assertContains('minLength', $exception->getMessage());
        $this->assertContains('notNumeric', $exception->getMessage());
    }

}