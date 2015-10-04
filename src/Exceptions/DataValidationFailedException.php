<?php
/**
 * Exception thrown when validation of entity data fails.
 * 
 * It contains a list of all validation errors (in the form of PropertyValidationFailedException's) under
 * getErrors() method.
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

class DataValidationFailedException extends InvalidArgumentException
{

    /**
     * Entity class for which the validation failed.
     * 
     * @var string
     */
    protected $entityClass;

    /**
     * List of errors that occurred during validation, in the form of PropertyValidationFailedException's.
     * 
     * @var array
     */
    protected $errors = array();

    /**
     * Constructor.
     * 
     * @param string $entityClass Entity class for which the validation failed.
     * @param array $errors List of errors that occurred during validation, in the form of PropertyValidationFailedException's.
     * @param int $code [optional] Optional code to help identify the exception.
     * @param Exception $previous [optional] Any previously thrown exception.
     */
    public function __construct($entityClass, array $errors, $code = 0, Exception $previous = null) {
        $this->entityClass = $entityClass;
        $this->errors = $errors;

        $message = 'Failed validating data for entity "'. $entityClass .'" ('. $this->getErrorsString() .')';
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns entity class for which the validation failed.
     *
     * @return string
     */
    public function getEntityClass() {
        return $this->entityClass;
    }

    /**
     * Returns list of errors that occurred during validation, in the form of PropertyValidationFailedException's.
     * 
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Converts the list of failed validators into a readable string.
     * 
     * @return string
     */
    public function getErrorsString() {
        $string = '';
        foreach($this->errors as $error) {
            $string .= $error->getProperty() .': ['. implode(', ', $error->getFailedValidators()) .']; ';
        }
        return trim($string, '; ');
    }

}