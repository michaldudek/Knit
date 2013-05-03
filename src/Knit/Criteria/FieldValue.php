<?php
/**
 * Criteria field value class.
 * 
 * @package Knit
 * @subpackage Criteria
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Criteria;

use Knit\Exceptions\InvalidOperatorException;

class FieldValue
{

    const OPERATOR_EQUALS = '__EQUALS__';
    const OPERATOR_NOT = '__NOT__';
    const OPERATOR_IN = '__IN__';
    const OPERATOR_GREATER_THAN = '__GREATHER_THAN__';
    const OPERATOR_GREATER_THAN_EQUAL = '__GREATHER_THAN_EQUAL__';
    const OPERATOR_LOWER_THAN = '__LOWER_THAN__';
    const OPERATOR_LOWER_THAN_EQUAL = '__LOWER_THAN_EQUAL__';

    /**
     * Name of the field.
     * 
     * @var string
     */
    protected $field;

    /**
     * Whatever value to use in comparison.
     * 
     * @var mixed
     */
    protected $value;

    /**
     * Operator that should be used in comparison.
     * 
     * @var string
     */
    protected $operator;

    /**
     * Constructor.
     * 
     * @param string $key Key of the expression that includes name of a field, a semi-colon and an operator, e.g. "id:not",
     *                    where only field name is mandatory.
     * @param mixed $value Whatever value the field should have.
     */
    public function __construct($key, $value) {
        // explode the key in search for an operator
        $explodedKey = explode(':', $key);

        // just specify the field name
        $this->field = $explodedKey[0];
        if (empty($this->field)) {
            throw new \InvalidArgumentException('Field name part of a key should not be empty in criteria expression, "'. $key .'" given.');
        }

        // if there was no operator specified then use the EQUALS operator by default
        $operator = (isset($explodedKey[1])) ? strtolower($explodedKey[1]) : 'eq';
        $operatorFn = $operator .'Operator';

        // if there is no function defined to handle this operator then throw an exception
        if (!method_exists($this, $operatorFn)) {
            throw new InvalidOperatorException('Unrecognized operator passed in criteria, "'. $key .'" given.');
        }
        
        $this->$operatorFn($value);
    }

    /*****************************************************
     * OPERATOR HANDLERS
     *****************************************************/
    /**
     * Handles EQUALS operator.
     * 
     * @param mixed $value
     */
    protected function eqOperator($value) {
        // if array passed then redirect to IN operator
        if (is_array($value)) {
            $this->inOperator($value);
            return;
        }

        $this->operator = self::OPERATOR_EQUALS;
        $this->value = $value;
    }

    /**
     * Handles NOT operator
     * 
     * @param mixed $value
     */
    protected function notOperator($value) {
        $this->operator = self::OPERATOR_NOT;
        $this->value = $value;
    }

    /**
     * Handles IN operator.
     * 
     * @param array $value Value has to be an array.
     */
    protected function inOperator(array $value) {
        $this->operator = self::OPERATOR_IN;
        $this->value = $value;
    }

    /**
     * Handles GREATER THAN operator.
     * 
     * @param mixed $value
     */
    protected function gtOperator($value) {
        $this->operator = self::OPERATOR_GREATER_THAN;
        $this->value = $value;
    }

    /**
     * Handles GREATER THAN EQUAL operator
     * 
     * @param mixed $value
     */
    protected function gteOperator($value) {
        $this->operator = self::OPERATOR_GREATER_THAN_EQUAL;
        $this->value = $value;
    }

    /**
     * Handles LOWER THAN operator
     * 
     * @param mixed $value
     */
    protected function ltOperator($value) {
        $this->operator = self::OPERATOR_LOWER_THAN;
        $this->value = $value;
    }

    /**
     * Handles LOWER THAN EQUAL operator
     * 
     * @param mixed $value
     */
    protected function lteOperator($value) {
        $this->operator = self::OPERATOR_LOWER_THAN_EQUAL;
        $this->value = $value;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns name of the field.
     * 
     * @return string
     */
    public function getField() {
        return $this->field;
    }

    /**
     * Returns whatever value should be used in the comparison.
     * 
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Returns the operator to be used in the comparison.
     * 
     * @return string
     */
    public function getOperator() {
        return $this->operator;
    }

}