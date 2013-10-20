<?php
/**
 * Validates if value doesn't exceed the max allowed length.
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

class MaxLengthValidator implements ValidatorInterface
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
        if (!is_string($value)) {
            $value = strval($value);
        }

        return mb_strlen($value) <= $against;
    }

}