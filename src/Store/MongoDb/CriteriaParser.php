<?php
namespace Knit\Store\MongoDb;

use MongoId;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\Exceptions\InvalidOperatorException;
use Knit\Knit;

/**
 * Converts Knit's `CriteriaExpression` object into MongoDb's criteria.
 *
 * @package    Knit
 * @subpackage Store\MongoDb
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
 */
class CriteriaParser
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
        //PropertyValue::OPERATOR_NOT_LIKE => 'notLike', // can't find a way to handle (?!.*) pattern...
        PropertyValue::OPERATOR_REGEX => 'regex'
    ];

    /**
     * Parses the `CriteriaExpression` into MongoDb criteria array.
     *
     * @param CriteriaExpression $criteria Knit's criteria expression to be parsed.
     *
     * @return array
     */
    public function parse(CriteriaExpression $criteria = null)
    {
        if (!$criteria || empty($criteria->getCriteria())) {
            return [];
        }

        return $this->parseCriteria($criteria);
    }

    /**
     * Does the actual work of parsing criteria.
     *
     * @param CriteriaExpression $criteria     Knit's criteria expression to be parsed.
     * @param boolean            $asCollection [optional] Should this criteria be parsed as a collection?
     *                                          Required for OR logic. For internal use. Default: `false`.
     *
     * @return array
     */
    private function parseCriteria(CriteriaExpression $criteria, $asCollection = false)
    {
        $result = [];

        foreach ($criteria->getCriteria() as $criterium) {
            if ($criterium instanceof CriteriaExpression) {
                $logic = $criterium->getLogic() === Knit::LOGIC_OR ? '$or' : '$and';

                $parsedCriterium = $this->parseCriteria($criterium, true);

                // if parent is OR then add this as a collection
                if ($criteria->getLogic() === Knit::LOGIC_OR) {
                    $result[][$logic] = $parsedCriterium;
                } else {
                    $result[$logic] = $parsedCriterium;
                }
                continue;
            }

            $result = $this->parseCriterium($criterium, $result, $asCollection);
        }

        return $result;
    }

    /**
     * Parses a single criterium.
     *
     * @param PropertyValue $criterium    Single criterium.
     * @param array         $result       Result of parsing criteria so far.
     * @param boolean       $asCollection [optional] Should this criteria be parsed as a collection?
     *                                    Required for OR logic. For internal use. Default: `false`.
     *
     * @return array
     */
    private function parseCriterium(PropertyValue $criterium, array $result, $asCollection = false)
    {
        $property = $criterium->getProperty();
        $operator = $criterium->getOperator();
        $value = $criterium->getValue();

        // to ensure compatibility with other stores, we treat `_id` and `id` the same
        if ($property === 'id') {
            $property = '_id';
        }

        // convert the `_id` property to MongoID
        if ($property === '_id') {
            $value = $this->convertToMongoId($value);
        }

        // merge operator with the value according to Mongo Api
        $value = $this->parseOperator($property, $operator, $value);

        if ($asCollection) {
            $result[] = [$property => $value];
        } else {
            $result[$property] = isset($result[$property])
                ? $this->mergeCriteria($result[$property], $value, $operator)
                : $value;
        }

        return $result;
    }

    /**
     * Merges criteria to an existing criteria for the same property.
     *
     * @param mixed  $criteria Existing criteria for a property.
     * @param mixed  $value    New filter value to be added.
     * @param string $operator Operator for the filter criteria to be added.
     *
     * @return array
     */
    private function mergeCriteria($criteria, $value, $operator)
    {
        // if expression for this property isn't an array then it needs to be converted to an equals expression
        if (!is_array($criteria)) {
            // using '$all' here as there's no '$eq' operator in Mongo
            $criteria = ['$all' => [$criteria]];
        }

        // fix analogously for the value itself
        if ($operator === PropertyValue::OPERATOR_EQUALS) {
            $value = ['$all' => [$value]];
        }

        // do in a foreach to get the operator (which is set to be key)
        foreach ($value as $operator => $val) {
            if (isset($criteria[$operator])) {
                $criteria[$operator] = is_array($criteria[$operator])
                    ? array_merge($criteria[$operator], $val)
                    : [$criteria[$operator], $val];
            } else {
                $criteria[$operator] = $val;
            }
        }

        return $criteria;
    }

    /**
     * Converts the given value to `MongoId` object (or array of those).
     *
     * @param string|array $value Value to be converted.
     *
     * @return MongoId|array
     */
    private function convertToMongoId($value)
    {
        if (is_array($value)) {
            $ids = [];

            foreach ($value as $id) {
                if (empty($id)) {
                    throw new \InvalidArgumentException('Cannot convert an empty value to MongoId object.');
                }
                $ids[] = new MongoId($id);
            }
            
            return $ids;
        }

        if (empty($value)) {
            throw new \InvalidArgumentException('Cannot convert an empty value to MongoId object.');
        }

        return new MongoId($value);
    }

    /**
     * Parses the operator and value pair into MongoDb format.
     *
     * @param string $property Property name.
     * @param string $operator Operator, one of `PropertyValue::OPERATOR_*` constants.
     * @param mixed  $value    Expected property value.
     *
     * @return mixed
     *
     * @throws InvalidOperatorException When not supported operator was passed.
     */
    private function parseOperator($property, $operator, $value)
    {
        if (!isset(self::$operatorExpressionMap[$operator])
            || !method_exists($this, self::$operatorExpressionMap[$operator])
        ) {
            throw new InvalidOperatorException(
                sprintf(
                    'MongoDBStore cannot handle operator %s',
                    trim($operator, '_')
                )
            );
        }

        $method = self::$operatorExpressionMap[$operator];
        return $this->{$method}($property, $value);
    }

    /**
     * Creates an EQUALS expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return mixed
     */
    private function equals($property, $value)
    {
        return $value;
    }

    /**
     * Creates a NOT EQUALS expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function notEquals($property, $value)
    {
        return ['$ne' => $value];
    }

    /**
     * Creates a IN expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    private function in($property, $value)
    {
        return ['$in' => $value];
    }

    /**
     * Creates a NOT IN expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function notIn($property, $value)
    {
        return ['$nin' => $value];
    }

    /**
     * Creates a GREATER THAN expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function greaterThan($property, $value)
    {
        return ['$gt' => $value];
    }

    /**
     * Creates a GREATER THAN EQUAL expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function greaterThanEqual($property, $value)
    {
        return ['$gte' => $value];
    }

    /**
     * Creates a LOWER THAN expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function lowerThan($property, $value)
    {
        return ['$lt' => $value];
    }

    /**
     * Creates a LOWER THAN EQUAL expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function lowerThanEqual($property, $value)
    {
        return ['$lte' => $value];
    }

    /**
     * Creates a LIKE expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function like($property, $value)
    {
        // replace non-escaped % to match-all
        $value = preg_replace('/(?<!\\\)%/', '.+', $value);
        // replace escaped % (\%) to normal %
        $value = preg_replace('/\\\%/', '%', $value);

        // and redirect to regex
        return $this->regex($property, $value);
    }

    /**
     * Creates a REGEX expression.
     *
     * @param string $property Property name.
     * @param mixed  $value    Property value.
     *
     * @return array
     */
    private function regex($property, $value)
    {
        $isRegex = preg_match('/^\/(.+)\/(\w*)/', $value, $matches) === 1;
        $value = $isRegex ? $matches[1] : $value;
        $options = $isRegex ? $matches[2] : 'i';
        return ['$regex' => $value, '$options' => $options];
    }
}
