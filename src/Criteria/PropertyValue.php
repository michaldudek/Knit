<?php
namespace Knit\Criteria;

use MD\Foundation\Utils\StringUtils;

use Knit\Exceptions\InvalidOperatorException;

/**
 * Criteria property value class.
 *
 * @package    Knit
 * @subpackage Criteria
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class PropertyValue
{
    /**
     * Operator constants.
     */
    const OPERATOR_EQUALS = '__EQUALS__';
    const OPERATOR_NOT = '__NOT__';
    const OPERATOR_IN = '__IN__';
    const OPERATOR_NOT_IN = '__NOT_IN__';
    const OPERATOR_GREATER_THAN = '__GREATHER_THAN__';
    const OPERATOR_GREATER_THAN_EQUAL = '__GREATHER_THAN_EQUAL__';
    const OPERATOR_LOWER_THAN = '__LOWER_THAN__';
    const OPERATOR_LOWER_THAN_EQUAL = '__LOWER_THAN_EQUAL__';
    const OPERATOR_REGEX = '__REGEX__';
    const OPERATOR_LIKE = '__LIKE__';
    const OPERATOR_NOT_LIKE = '__NOT_LIKE__';
    const OPERATOR_EXISTS = '__EXISTS__';

    /**
     * Property name.
     *
     * @var string
     */
    protected $property;

    /**
     * Criteria value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Criteria operator.
     *
     * @var string
     */
    protected $operator;

    /**
     * Constructor.
     *
     * @param string $key Criteria expression key that includes property name and an optional operator prefixed with `:`
     *                    e.g. `id:not`.
     * @param mixed $value Value for criteria filter.
     *
     * @throws InvalidOperatorException When invalid or unsupported operator given.
     */
    public function __construct($key, $value)
    {
        // explode the key in search for an operator
        $explodedKey = explode(':', $key);

        // just specify the property name
        $this->property = $explodedKey[0];
        if (empty($this->property)) {
            throw new \InvalidArgumentException(
                sprintf('Property name part of a key should not be empty in criteria expression, "%s" given.', $key)
            );
        }

        // if there was no operator specified then use the EQUALS operator by default
        $operator = (isset($explodedKey[1])) ? strtolower($explodedKey[1]) : 'eq';
        $operatorFn = StringUtils::toCamelCase($operator, '_') .'Operator';

        // if there is no function defined to handle this operator then throw an exception
        if (!method_exists($this, $operatorFn)) {
            throw new InvalidOperatorException(sprintf('Unrecognized operator passed in criteria, "%s" given.', $key));
        }
        
        $this->$operatorFn($value);
    }

    /*****************************************************
     * OPERATOR HANDLERS
     *****************************************************/
    /**
     * Handles EQUALS operator.
     *
     * @param mixed $value Criteria value.
     */
    protected function eqOperator($value)
    {
        $this->operator = self::OPERATOR_EQUALS;
        $this->value = $value;
    }

    /**
     * Handles NOT operator
     *
     * @param mixed $value Criteria value.
     */
    protected function notOperator($value)
    {
        $this->operator = self::OPERATOR_NOT;
        $this->value = $value;
    }

    /**
     * Handles IN operator.
     *
     * @param array $value Criteria value.
     */
    protected function inOperator(array $value)
    {
        $this->operator = self::OPERATOR_IN;
        $this->value = $value;
    }

    /**
     * Handles NOT IN operator.
     *
     * @param array  $value Criteria value.
     */
    protected function notInOperator(array $value)
    {
        $this->operator = self::OPERATOR_NOT_IN;
        $this->value = $value;
    }

    /**
     * Handles GREATER THAN operator.
     *
     * @param mixed $value Criteria value.
     */
    protected function gtOperator($value)
    {
        $this->operator = self::OPERATOR_GREATER_THAN;
        $this->value = $value;
    }

    /**
     * Handles GREATER THAN EQUAL operator
     *
     * @param mixed $value Criteria value.
     */
    protected function gteOperator($value)
    {
        $this->operator = self::OPERATOR_GREATER_THAN_EQUAL;
        $this->value = $value;
    }

    /**
     * Handles LOWER THAN operator
     *
     * @param mixed $value Criteria value.
     */
    protected function ltOperator($value)
    {
        $this->operator = self::OPERATOR_LOWER_THAN;
        $this->value = $value;
    }

    /**
     * Handles LOWER THAN EQUAL operator
     *
     * @param mixed $value Criteria value.
     */
    protected function lteOperator($value)
    {
        $this->operator = self::OPERATOR_LOWER_THAN_EQUAL;
        $this->value = $value;
    }

    /**
     * Handles REGEX operator.
     *
     * @param string $value Criteria value.
     */
    protected function regexOperator($value)
    {
        $this->operator = self::OPERATOR_REGEX;
        $this->value = $value;
    }

    /**
     * Handles LIKE operator.
     *
     * @param string $value Criteria value.
     */
    protected function likeOperator($value)
    {
        $this->operator = self::OPERATOR_LIKE;
        $this->value = $value;
    }

    /**
     * Handles NOT LIKE operator.
     *
     * @param string $value Criteria value.
     */
    protected function notLikeOperator($value)
    {
        $this->operator = self::OPERATOR_NOT_LIKE;
        $this->value = $value;
    }

    /**
     * Handles EXISTS operator.
     *
     * @param string $value Criteria value.
     */
    protected function existsOperator($value)
    {
        $this->operator = self::OPERATOR_EXISTS;
        $this->value = $value;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns criteria property name.
     *
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * Returns criteria property value.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the criteria operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }
}
