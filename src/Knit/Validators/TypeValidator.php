<?php
/**
 * Validates if the given value is of valid type.
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
use Knit\KnitOptions;

class TypeValidator implements ValidatorInterface
{

    /**
     * Performs the validation.
     * 
     * @param mixed $value Value to be validated.
     * @param mixed $against Against what value to validate?
     * @param AbstractEntity $entity [optional] Entity for which the validation happens.
     * @return bool
     */
    public function validate($value, $against = null, AbstractEntity $entity = null) {
        switch($against) {
            case KnitOptions::TYPE_INT:
                return is_int($value) || is_numeric($value);
            break;

            case KnitOptions::TYPE_STRING:
                return is_string($value);
            break;

            default:
                return true;
        }

        return false;
    }

}