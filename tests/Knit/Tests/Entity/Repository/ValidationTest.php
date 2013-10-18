<?php
namespace Knit\Tests\Entity\Repository;

use Knit\Tests\Fixtures\Dummy;
use Knit\Tests\Fixtures\Standard;
use Knit\Tests\Fixtures\ValidatingEntity;

use Knit\Entity\Repository;
use Knit\Exceptions\DataValidationFailedException;
use Knit\Exceptions\PropertyValidationFailedException;

class ValidationTest extends \PHPUnit_Framework_TestCase
{


    /**
     * @dataProvider providePropertyValidationData
     * @covers \Knit\Entity\Repository::validateProperty
     */
    public function testValidatingProperty($property, $value, $valid, array $shouldFail = array()) {
        $self = $this;
        $mocks = $this->provideMocks();
        
        $mocks['knit']->expects($this->any())
            ->method('getValidator')
            ->will($this->returnCallback(function($name) use ($valid, $shouldFail, $self) {
                $validator = $self->getMock('Knit\Validators\ValidatorInterface');
                $validator->expects($self->any())
                    ->method('validate')
                    ->will($self->returnValue(!in_array($name, $shouldFail)));
                return $validator;
            }));

        $repository = $this->provideRepository(ValidatingEntity::__class(), $mocks);

        $exceptionThrown = false;
        $exception = null;
        $result = false;
        try {
            $result = $repository->validateProperty($property, $value);
        } catch(PropertyValidationFailedException $e) {
            $exceptionThrown = true;
            $exception = $e;
        }

        if ($valid) {
            $this->assertTrue($result);
            $this->assertFalse($exceptionThrown, 'Failed asserting that validation exception has NOT been thrown when validating a valid value');
        } else {
            $this->assertTrue($exceptionThrown, 'Failed asserting that validation exception has been thrown when validating an invalid value');

            // also make sure proper data in the exception are set (test the exception)
            $this->assertNotNull($exception);
            $this->assertEquals(ValidatingEntity::__class(), $exception->getEntityClass());
            $this->assertEquals($property, $exception->getProperty());
            $this->assertEquals($value, $exception->getValue());
            $this->assertInternalType('array', $exception->getFailedValidators());
            $this->assertNotEmpty($exception->getFailedValidators());
            foreach($shouldFail as $validator) {
                $this->assertContains($validator, $exception->getFailedValidators());
            }
        }
    }

    /**
     * @depends testValidatingProperty
     * @covers \Knit\Entity\Repository::validateData
     * @dataProvider provideDataValidationData
     */
    public function testValidatingData(array $data, $valid, array $errors = array()) {
        $self = $this;
        $mocks = $this->provideMocks();

        $mocks['knit']->expects($this->any())
            ->method('getValidator')
            ->will($this->returnCallback(function($name) use ($errors, $data, $self) {
                $validator = $self->getMock('Knit\Validators\ValidatorInterface');
                $validator->expects($self->any())
                    ->method('validate')
                    ->will($self->returnCallback(function($val) use ($name, $data, $errors) {
                        $property = array_search($val, $data);
                        if (!$property) {
                            return true;
                        }

                        if (!isset($errors[$property])) {
                            return true;
                        }

                        if (!is_array($errors[$property])) {
                            return true;
                        }

                        if (!in_array($name, $errors[$property])) {
                            return true;
                        }

                        return false;
                    }));
                return $validator;
            }));

        $repository = $this->provideRepository(ValidatingEntity::__class(), $mocks);

        $exceptionThrown = false;
        $exception = null;
        $result = false;

        try {
            $result = $repository->validateData($data);
        } catch(DataValidationFailedException $e) {
            $exceptionThrown = true;
            $exception = $e;
        }

        if ($valid) {
            $this->assertTrue($result);
            $this->assertFalse($exceptionThrown, 'Failed asserting that validation exception has NOT been thrown for valid data');
        } else {
            $this->assertTrue($exceptionThrown, 'Failed asserting that validation exception has been thrown for invalid data');

            $this->assertNotNull($exception);
            $this->assertEquals(ValidatingEntity::__class(), $exception->getEntityClass());

            // also check if the thrown exception contains all other validation exceptions
            $this->assertContainsOnlyInstancesOf('Knit\Exceptions\PropertyValidationFailedException', $exception->getErrors());
        }
    }

    public function providePropertyValidationData() {
        return array(
            array('id', 1, true),
            array('id', 'abc', false, array('type')),
            array('slug', 'lorem-ipsum', true),
            array('slug', 12, false, array('type', 'minLength')),
            array('slug', null, false, array('type', 'minLength', 'required')),
            array('slug', 'lorem ipsum dolor sit amet adipiscit elit', false, array('maxLength', 'urlFriendly')),
            array('time_created', time(), true),
            array('time_created', 'abcd', false, array('type', 'min', 'max')),
            array('time_created', 1234, false, array('min')),
            array('time_created', 99999999999, false, array('max')),
            array('not_persisted', 'hahaha', true)
        );
    }

    public function provideDataValidationData() {
        return array(
            array(array(
                'id' => 1,
                'slug' => 'lorem-ipsum',
                'time_created' => time()
            ), true, array()),
            array(array(
                'id' => 'abc',
                'slug' => 12,
                'time_created' => 'abcd'
            ), false, array('id' => array('type'), 'slug' => array('type', 'minLength'), 'time_created' => array('type', 'min', 'max'))),
            array(array(
                'id' => 1,
                'slug' => 12,
                'time_created' => 12345
            ), false, array('slug' => array('type', 'minLength'), 'time_created' => array('min'))),
            array(array(
                'id' => 56,
                'slug' => 'lorem-ipsum-dolor-sit-amet-adipiscit',
                'time_created' => time()
            ), false, array('slug' => array('minLength')))
        );
    }


    protected function provideMocks() {
        $mocks = array(
            'store' => $this->getMock('Knit\Store\StoreInterface'),
            'knit' => $this->getMockBuilder('Knit\Knit')
                ->disableOriginalConstructor()
                ->getMock()
        );
        return $mocks;
    }

    protected function provideRepository($entityClass = '\Knit\Tests\Fixtures\Standard', array $mocks = array()) {
        $mocks = (!empty($mocks)) ? $mocks : $this->provideMocks();
        return new Repository($entityClass, $mocks['store'], $mocks['knit']);
    }

}