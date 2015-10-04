<?php
/**
 * Exception thrown when validation of an entity property fails.
 * 
 * @package Knit
 * @subpackage Exceptions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Exceptions;

use Exception;
use InvalidArgumentException;

class PropertyValidationFailedException extends InvalidArgumentException
{

    /**
     * Entity class for which the validation failed.
     * 
     * @var string
     */
    protected $entityClass;

    /**
     * Property name for which the validation failed.
     * 
     * @var string
     */
    protected $property;

    /**
     * Value that was being tried to be set for the property.
     * 
     * @var mixed
     */
    protected $value;

    /**
     * List of validators that rejected the data.
     * 
     * @var array
     */
    protected $failedValidators = array();

    /**
     * Constructor.
     * 
     * @param string $entityClass Entity class for which the validation failed.
     * @param string $property Property name for which the validation failed.
     * @param mixed $value Value for which the validation failed.
     * @param array $failedValidators List of validators that rejected the data.
     * @param int $code [optional] Optional code to help identify the exception.
     * @param Exception $previous [optional] Any previously thrown exception.
     */
    public function __construct($entityClass, $property, $value, array $failedValidators, $code = 0, Exception $previous = null) {
        $message = 'Failed validating value "'. $value .'" for property "'. $entityClass .'::'. $property .'" by validators: '. implode(', ', $failedValidators);
        parent::__construct($message, $code, $previous);

        $this->entityClass = $entityClass;
        $this->property = $property;
        $this->value = $value;
        $this->failedValidators = $failedValidators;
    }

    /**
     * Returns the entity class for which the validation failed.
     *
     * @return string
     */
    public function getEntityClass() {
        return $this->entityClass;
    }

    /**
     * Returns property name for which the validation failed.
     * 
     * @return string
     */
    public function getProperty() {
        return $this->property;
    }

    /**
     * Returns value that was being tried to be set for the property.
     * 
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Returns list of validators that rejected the data.
     * 
     * @return array
     */
    public function getFailedValidators() {
        return $this->failedValidators;
    }

}