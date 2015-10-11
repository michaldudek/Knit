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

            case PropertyValue::OPERATOR_NOT_IN:
                $result = ['$nin' => $value];
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

            case PropertyValue::OPERATOR_LIKE:
                // replace non-escaped % to match-all
                $value = preg_replace('/(?<!\\\)%/', '.+', $value);
                // replace escaped % (\%) to normal %
                $value = preg_replace('/\\\%/', '%', $value);
                // and fall through to regex handle, as the rest is handled like a regex

            case PropertyValue::OPERATOR_REGEX:
                $isRegex = preg_match('/^\/(.+)\/(\w*)/', $value, $matches) === 1;
                $value = $isRegex ? $matches[1] : $value;
                $options = $isRegex ? $matches[2] : 'i';
                $result = ['$regex' => $value, '$options' => $options];
                break;

            // if it hasn't been handled by the above then throw an exception
            case PropertyValue::OPERATOR_NOT_LIKE: // can't find a way to handle (?!.*) pattern...
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
