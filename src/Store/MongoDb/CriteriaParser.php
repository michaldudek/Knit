<?php
namespace Knit\Store\MongoDb;

use MongoId;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\Exceptions\InvalidOperatorException;
use Knit\KnitOptions;

/**
 * Converts Knit's `CriteriaExpression` object into MongoDb's criteria.
 *
 * @package    Knit
 * @subpackage Store\MongoDb
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class CriteriaParser
{
    /**
     * Parses the `CriteriaExpression` into MongoDb criteria array.
     *
     * @param CriteriaExpression $criteria Knit's criteria expression to be parsed.
     *
     * @return array
     */
    public function parse(CriteriaExpression $criteria = null)
    {
        if (!$criteria) {
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
                $logic = $criterium->getLogic() === KnitOptions::LOGIC_OR ? '$or' : '$and';
                $result[$logic] = $this->parseCriteria($criterium, true);
                continue;
            }

            $property = $criterium->getProperty();
            $operator = $criterium->getOperator();
            $value = $criterium->getValue();

            // convert the `_id` property to MongoID
            if ($property === '_id') {
                $value = $this->convertToMongoId($value);
            }

            // merge operator with the value according to Mongo Api
            $value = $this->parseOperator($operator, $value);

            if ($asCollection) {
                $result[] = [$property => $value];
                continue;
            }

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

        // fix analogusly for the value itself
        if ($operator === PropertyValue::OPERATOR_EQUALS) {
            $value = ['$all' => [$value]];
        }

        // do in a foreach to get the operator (which is set to be key)
        foreach ($value as $operator => $val) {
            $criteria[$operator] = $val;
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
                $ids[] = new MongoId($id);
            }
            
            return $ids;
        }

        return new MongoId($value);
    }

    /**
     * Parses the operator and value pair into MongoDb format.
     *
     * @param string $operator Operator, one of `PropertyValue::OPERATOR_*` constants.
     * @param mixed  $value    Expected property value.
     *
     * @return mixed
     *
     * @throws InvalidOperatorException When not supported operator was passed.
     */
    private function parseOperator($operator, $value)
    {
        $result = $value;

        switch ($operator) {
            case PropertyValue::OPERATOR_EQUALS:
                $result = $value;
                break;

            case PropertyValue::OPERATOR_NOT:
                $result = ['$ne' => $value];
                break;

            case PropertyValue::OPERATOR_IN:
                $result = ['$in' => $value];
                break;

            case PropertyValue::OPERATOR_GREATER_THAN:
                $result = ['$gt' => $value];
                break;

            case PropertyValue::OPERATOR_GREATER_THAN_EQUAL:
                $result = ['$gte' => $value];
                break;

            case PropertyValue::OPERATOR_LOWER_THAN:
                $result = ['$lt' => $value];
                break;

            case PropertyValue::OPERATOR_LOWER_THAN_EQUAL:
                $result = ['$lte' => $value];
                break;

            // if it hasn't been handled by the above then throw an exception
            default:
                throw new InvalidOperatorException(
                    sprintf(
                        'MongoDBStore cannot handle operator %s',
                        trim($operator, '_')
                    )
                );
        }

        return $result;
    }
}
