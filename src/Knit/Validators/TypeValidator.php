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
use Knit\Entity\Repository;
use Knit\Validators\ValidatorInterface;
use Knit\KnitOptions;

class TypeValidator implements ValidatorInterface
{

    /**
     * Performs the validation of the given value against the optional criteria for the optional entity.
     * 
     * Should return (bool) true if validation was successful or (bool) false if not.
     * 
     * @param mixed $value Value to be validated.
     * @param mixed $against [optional] Optional against value (taken from entity's structure configuration).
     * @param string $property [optional] Name of the property that is being validated.
     * @param AbstractEntity $entity [optional] Entity for which the validation happens.
     * @param Repository $repository [optional] Repository of the entity that is being validated.
     * @return bool
     */
    public function validate($value, $against = null, $property = null, AbstractEntity $entity = null, Repository $repository = null) {
        if (is_null($value)) {
            return true;
        }
        
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