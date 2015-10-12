<?php
namespace Knit\Store\DoctrineDBAL;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\Exceptions\InvalidOperatorException;
use Knit\Knit;

/**
 * Converts Knit's `CriteriaExpression` object into statements on Doctrine's Query Builder.
 *
 * @package    Knit
 * @subpackage Store\DoctrineDBAL
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
 */
class CriteriaParser
{
    /**
     * Prefix added to all criteria parameters so they don't overwrite value parameters.
     */
    const PARAM_PREFIX = 'knit_filter__';

    /**
     * Counts how many parameters have already been bound, so each created name is unique.
     *
     * @var integer
     */
    private static $boundCounter = 0;

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
        PropertyValue::OPERATOR_NOT_LIKE => 'notLike'
    ];

    /**
     * Parses the `CriteriaExpression` and appropriately configures the passed query builder.
     *
     * @param QueryBuilder            $queryBuilder Query builder to be configured.
     * @param CriteriaExpression|null $criteria     Criteria to be parsed.
     *
     * @return QueryBuilder
     */
    public function parse(QueryBuilder $queryBuilder, CriteriaExpression $criteria = null)
    {
        if (!$criteria || count($criteria->getCriteria()) === 0) {
            return $queryBuilder;
        }

        $queryBuilder->andWhere($this->parseCriteria($queryBuilder, $criteria));

        return $queryBuilder;
    }

    /**
     * Parses `CriteriaExpression` to a set of expressions.
     *
     * @param QueryBuilder       $queryBuilder Query builder to be configured.
     * @param CriteriaExpression $criteria     Criteria to be parsed.
     *
     * @return CompositeExpression
     */
    private function parseCriteria(QueryBuilder $queryBuilder, CriteriaExpression $criteria)
    {
        $expressions = [];
        foreach ($criteria->getCriteria() as $criterium) {
            if ($criterium instanceof CriteriaExpression) {
                $expressions[] = $this->parseCriteria($queryBuilder, $criterium);
                continue;
            }

            $expressions[] = $this->createExpression($queryBuilder, $criterium);
        }

        // now join all the expressions by the predefined logic
        $logic = $criteria->getLogic() === Knit::LOGIC_OR ? 'orX' : 'andX';
        return call_user_func_array([$queryBuilder->expr(), $logic], $expressions);
    }

    /**
     * Creates a single expression based on the passed criterium and sets appropriate parameters
     * in the query builder.
     *
     * @param QueryBuilder  $queryBuilder Query builder to be configured.
     * @param PropertyValue $criterium    Single criterium.
     *
     * @return string
     *
     * @throws InvalidOperatorException When an unsupported operator was used.
     */
    private function createExpression(QueryBuilder $queryBuilder, PropertyValue $criterium)
    {
        $property = $criterium->getProperty();
        $operator = $criterium->getOperator();
        $value = $criterium->getValue();

        if (!isset(self::$operatorExpressionMap[$operator])
            || !method_exists($this, self::$operatorExpressionMap[$operator])
        ) {
            throw new InvalidOperatorException(
                sprintf(
                    'DoctrineDBAL cannot handle operator %s',
                    trim($operator, '_')
                )
            );
        }

        $method = self::$operatorExpressionMap[$operator];
        return $this->{$method}($queryBuilder, $property, $value);
    }

    /**
     * Creates an expression for the given operator and sets appropriate parameter on the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $operator     Expression operator.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function expr(QueryBuilder $queryBuilder, $operator, $property, $value)
    {
        $parameter = $this->createParameter($property);
        $expression = $queryBuilder->expr()->{$operator}($property, ':'. $parameter);
        $queryBuilder->setParameter($parameter, $value);
        return $expression;
    }

    /**
     * Creates an EQUALS expression for the given query builder by optionally redirecting to IN expression
     * if array value was given, or IS NULL operator if null value was given.
     *
     * Otherwise normally creates an expression and assigns appropriate parameter.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function equals(QueryBuilder $queryBuilder, $property, $value)
    {
        if (is_array($value)) {
            return $this->in($queryBuilder, $property, $value);
        }

        if ($value === null) {
            return $queryBuilder->expr()->isNull($property);
        }

        return $this->expr($queryBuilder, 'eq', $property, $value);
    }

    /**
     * Creates a NOT EQUALS expression for the given query builder by optionally redirecting to NOT IN expression
     * if array value was given, or IS NOT NULL operator if null value was given.
     *
     * Otherwise normally creates an expression.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function notEquals(QueryBuilder $queryBuilder, $property, $value)
    {
        if (is_array($value)) {
            return $this->notIn($queryBuilder, $property, $value);
        }

        if ($value === null) {
            return $queryBuilder->expr()->isNotNull($property);
        }

        return $this->expr($queryBuilder, 'neq', $property, $value);
    }

    /**
     * Creates an IN expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param array        $value        Value for the right side of the operator.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    private function in(QueryBuilder $queryBuilder, $property, array $value)
    {
        // if empty IN just return 0 (serving as FALSE) to never match
        if (empty($value)) {
            return 'FALSE';
        }

        return $queryBuilder->expr()->in($property, $this->createInValue($value));
    }

    /**
     * Creates a NOT IN expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param array        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function notIn(QueryBuilder $queryBuilder, $property, array $value)
    {
        // if empty NOT IN just return 1 (serving as TRUE) to always match
        if (empty($value)) {
            return 'TRUE';
        }

        return $queryBuilder->expr()->notIn($property, $this->createInValue($value));
    }

    /**
     * Creates a GREATER THAN expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function greaterThan(QueryBuilder $queryBuilder, $property, $value)
    {
        return $this->expr($queryBuilder, 'gt', $property, $value);
    }

    /**
     * Creates a GREATER THAN EQUAL expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function greaterThanEqual(QueryBuilder $queryBuilder, $property, $value)
    {
        return $this->expr($queryBuilder, 'gte', $property, $value);
    }

    /**
     * Creates a LOWER THAN expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function lowerThan(QueryBuilder $queryBuilder, $property, $value)
    {
        return $this->expr($queryBuilder, 'lt', $property, $value);
    }

    /**
     * Creates a LOWER THAN EQUAL expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function lowerThanEqual(QueryBuilder $queryBuilder, $property, $value)
    {
        return $this->expr($queryBuilder, 'lte', $property, $value);
    }

    /**
     * Creates a LIKE expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function like(QueryBuilder $queryBuilder, $property, $value)
    {
        return $this->expr($queryBuilder, 'like', $property, $value);
    }

    /**
     * Creates a NOT LIKE expression for the given query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder to be configured.
     * @param string       $property     Property name.
     * @param mixed        $value        Value for the right side of the operator.
     *
     * @return string
     */
    private function notLike(QueryBuilder $queryBuilder, $property, $value)
    {
        return $this->expr($queryBuilder, 'notLike', $property, $value);
    }

    /**
     * Creates a unique parameter name to be used in `QueryBuilder`.
     *
     * @param string $name Base parameter name, usually a column name.
     *
     * @return string
     */
    private function createParameter($name)
    {
        $number = self::$boundCounter++;
        return self::PARAM_PREFIX . $name . $number;
    }

    /**
     * Creates a string to put inside IN () statement.
     *
     * @param array $values Values.
     *
     * @return string
     */
    private function createInValue(array $values)
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = is_int($value) ? $value : "'". $value ."'";
        }
        return implode(',', $items);
    }
}
