<?php
/**
 * Validates if value is greater or equal than the allowed minimum.
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

class MinValidator implements ValidatorInterface
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
        if (!is_int($value)) {
            $value = intval($value);
        }

        return $value >= $against;
    }

}