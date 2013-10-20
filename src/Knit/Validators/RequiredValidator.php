<?php
/**
 * Validates if the given value is not empty or null.
 * 
 * @package Knit
 * @subpackage Validators
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Validators;

use Knit\Entity\AbstractEntity;
use Knit\Validators\ValidatorInterface;

class RequiredValidator implements ValidatorInterface
{

    /**
     * Performs the validation.
     * 
     * @param mixed $value Value to be validated.
     * @param mixed $required Is the value required?
     * @param AbstractEntity $entity [optional] Entity for which the validation happens.
     * @return bool
     */
    public function validate($value, $required = null, AbstractEntity $entity = null) {
        // if not required then automatically pass the validation
        if ($required === null || $required === false) {
            return true;
        }

        if ($value === null) {
            return false;
        }

        if (is_int($value) && $value === 0) {
            return true;
        }

        if (is_string($value) && $value === '0') {
            return true;
        }

        $value = trim($value);

        return !empty($value);
    }

}