<?php
namespace Knit\Store\Memory;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\Exceptions\InvalidOperatorException;
use Knit\Knit;

/**
 * Criteria matcher.
 *
 * @package    Knit
 * @subpackage Store\Memory
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2016 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
 */
class CriteriaMatcher
{
    /**
     * Map of operators and functions that create appropriate expressions.
     *
     * @var array
     */
    private static $operatorExpressionMap = [
        PropertyValue::OPERATOR_EQUALS => 'equals',
        PropertyValue::OPERATOR_NOT => 'notEquals',
        PropertyValue::OPERATOR_IN => 'in',
        PropertyValue::OPERATOR_NOT_IN => 'notIn',
        PropertyValue::OPERATOR_GREATER_THAN => 'greaterThan',
        PropertyValue::OPERATOR_GREATER_THAN_EQUAL => 'greaterThanEqual',
        PropertyValue::OPERATOR_LOWER_THAN => 'lowerThan',
        PropertyValue::OPERATOR_LOWER_THAN_EQUAL => 'lowerThanEqual',
        PropertyValue::OPERATOR_LIKE => 'like',
        PropertyValue::OPERATOR_NOT_LIKE => 'notLike',
        PropertyValue::OPERATOR_REGEX => 'regex'
    ];

    /**
     * Checks if the given object matches the given criteria.
     *
     * @param array              $object   Object to check.
     * @param CriteriaExpression $criteria Criteria expression to check against. Optional.
     *
     * @return bool
     */
    public function matches(array $object, CriteriaExpression $criteria = null)
    {
        if (!$criteria || count($criteria->getCriteria()) === 0) {
            return true;
        }

        $matches = [];
        foreach ($criteria->getCriteria() as $criterium) {
            if ($criterium instanceof CriteriaExpression) {
                $matches[] = $this->matches($object, $criterium);
                continue;
            }

            $matches[] = $this->matchesProperty($object, $criterium);
        }

        // map bool's to ints so they can be summed
        $matches = array_map('intval', $matches);
        $sum = array_sum($matches);

        return $criteria->getLogic() === Knit::LOGIC_AND
            ? $sum === count($matches) // everything must match for AND
            : $sum > 0; // at least 1 must match for OR
    }

    /**
     * Checks if the object's property matches the given criterium.
     *
     * @param array         $object    Object (transformed to array) to match against.
     * @param PropertyValue $criterium Single criterium.
     *
     * @return bool
     *
     * @throws InvalidOperatorException When an unsupported operator was used.
     */
    private function matchesProperty(array $object, PropertyValue $criterium)
    {
        $property = $criterium->getProperty();
        $operator = $criterium->getOperator();
        $value = $criterium->getValue();

        if (!isset(self::$operatorExpressionMap[$operator])
            || !method_exists($this, self::$operatorExpressionMap[$operator])
        ) {
            throw new InvalidOperatorException(
                sprintf(
                    'Memory CriteriaMatcher cannot handle operator %s',
                    trim($operator, '_')
                )
            );
        }

        $method = self::$operatorExpressionMap[$operator];
        return $this->{$method}($object, $property, $value);
    }

    /**
     * Matches object on EQUALS operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function equals(array $object, $property, $value)
    {
        if (is_array($value)) {
            return $this->in($object, $property, $value);
        }

        if ($value === null) {
            return !isset($object[$property]) || $object[$property] === null;
        }

        return isset($object[$property]) && $object[$property] == $value; // weak match on purpose
    }

    /**
     * Matches object on NOT EQUALS operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function notEquals(array $object, $property, $value)
    {
        if (is_array($value)) {
            return $this->notIn($object, $property, $value);
        }

        if ($value === null) {
            return isset($object[$property]) && $object[$property] !== null;
        }

        return !isset($object[$property]) || $object[$property] != $value; // weak match on purpose
    }

    /**
     * Matches object on IN operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param array  $value    Value to match against.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    private function in(array $object, $property, array $value)
    {
        if (empty($value)) {
            return false;
        }

        return isset($object[$property]) && in_array($object[$property], $value);
    }

    /**
     * Matches object on NOT IN operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param array  $value    Value to match against.
     *
     * @return bool
     */
    private function notIn(array $object, $property, array $value)
    {
        if (empty($value)) {
            return true;
        }

        return !isset($object[$property]) || !in_array($object[$property], $value);
    }

    /**
     * Matches object on GREATER THAN operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function greaterThan(array $object, $property, $value)
    {
        return isset($object[$property]) && $object[$property] > $value;
    }

    /**
     * Matches object on GREATER THAN EQUAL operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function greaterThanEqual(array $object, $property, $value)
    {
        return isset($object[$property]) && $object[$property] >= $value;
    }

    /**
     * Matches object on LOWER THAN operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function lowerThan(array $object, $property, $value)
    {
        return isset($object[$property]) && $object[$property] < $value;
    }

    /**
     * Matches object on LOWER THAN EQUAL operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function lowerThanEqual(array $object, $property, $value)
    {
        return isset($object[$property]) && $object[$property] <= $value;
    }

    /**
     * Matches object on LIKE operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function like(array $object, $property, $value)
    {
        // replace non-escaped % to match-all
        $value = preg_replace('/(?<!\\\)%/', '.*', $value);
        // replace escaped % (\%) to normal %
        $value = preg_replace('/\\\%/', '%', $value);

        return isset($object[$property]) && $this->regex($object, $property, $value);
    }

    /**
     * Matches object on NOT LIKE operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function notLike(array $object, $property, $value)
    {
        // replace non-escaped % to match-all
        $value = preg_replace('/(?<!\\\)%/', '.*', $value);
        // replace escaped % (\%) to normal %
        $value = preg_replace('/\\\%/', '%', $value);

        return !isset($object[$property]) || !$this->regex($object, $property, $value);
    }

    /**
     * Matches object on REGEX operator.
     *
     * @param array  $object   Object to match.
     * @param string $property Object's property to match.
     * @param mixed  $value    Value to match against.
     *
     * @return bool
     */
    private function regex(array $object, $property, $value)
    {
        if (!isset($object[$property])) {
            return false;
        }

        $isRegex = preg_match('/^\/(.+)\/(\w*)/', $value, $matches) === 1;
        $value = $isRegex ? $matches[1] : $value;
        $options = $isRegex ? $matches[2] : 'i';

        return preg_match('/'. $value .'/'. $options, $object[$property]) === 1;
    }
}
