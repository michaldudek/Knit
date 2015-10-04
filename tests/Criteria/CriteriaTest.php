<?php
namespace Knit\Tests\Criteria;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\KnitOptions;

/**
 * Tests generating criteria expressions objects from arrays.
 *
 * @package    Knit
 * @subpackage Criteria
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @covers Knit\Criteria\CriteriaExpression
 * @covers Knit\Criteria\PropertyValue
 */
class CriteriaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests generating the criteria object.
     *
     * @param array $criteria Criteria array.
     * @param array $expected Expected result.
     *
     * @dataProvider provideCriteria
     */
    public function testCriteria(array $criteria, array $expected)
    {
        $expression = new CriteriaExpression($criteria);

        $validator = function (CriteriaExpression $expression, $expected, $validator) {
            $this->assertEquals($expected['logic'], $expression->getLogic());

            $criteria = $expression->getCriteria();
            foreach ($criteria as $i => $criterium) {
                $expectedItem = $expected['properties'][$i];

                if ($expectedItem[0] === 'criteria') {
                    $validator($criterium, $expectedItem[1], $validator);
                } elseif ($expectedItem[0] === 'property') {
                    $this->assertInstanceOf(PropertyValue::class, $criterium);
                    $this->assertEquals($expectedItem[1], $criterium->getProperty());
                    $this->assertEquals($expectedItem[2], $criterium->getOperator());
                    $this->assertEquals($expectedItem[3], $criterium->getValue());
                }
            }
        };

        $validator($expression, $expected, $validator);
    }

    /**
     * Tests passing invalid logic.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidLogic()
    {
        $expression = new CriteriaExpression([], 'invalid');
    }

    /**
     * Tests omitting property name.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidCriteriaKey()
    {
        $expression = new CriteriaExpression([':not' => 5]);
    }

    /**
     * Tests using invalid operator.
     *
     * @expectedException \Knit\Exceptions\InvalidOperatorException
     */
    public function testInvalidOperator()
    {
        $expression = new CriteriaExpression(['id:roughly' => 15]);
    }

    /**
     * Provides criteria data sets for testing.
     *
     * @return array
     */
    public function provideCriteria()
    {
        return [
            [ // #0 - single criteria
                'criteria' => [
                    'id' => 5
                ],
                'expected' => [
                    'logic' => KnitOptions::LOGIC_AND,
                    'properties' => [
                        ['property', 'id', PropertyValue::OPERATOR_EQUALS, 5]
                    ]
                ]
            ],
            [ // #1 - multiple criteria
                'criteria' => [
                    'id' => 5,
                    'deleted:not' => 1
                ],
                'expected' => [
                    'logic' => KnitOptions::LOGIC_AND,
                    'properties' => [
                        ['property', 'id', PropertyValue::OPERATOR_EQUALS, 5],
                        ['property', 'deleted', PropertyValue::OPERATOR_NOT, 1]
                    ]
                ]
            ],
            [ // #2 - multiple criteria - all operators
                'criteria' => [
                    'type' => 'fellowship_member',
                    'race:not' => 'human',
                    'name:in' => ['Frodo', 'Sam', 'Pippin', 'Merry'],
                    'name' => ['Gandalf', 'Gimli', 'Legolas', 'Aragorn'],
                    'age:gt' => 50,
                    'height:gte' => 160,
                    'size:lt' => 15,
                    'length:lte' => 23
                ],
                'expected' => [
                    'logic' => KnitOptions::LOGIC_AND,
                    'properties' => [
                        ['property', 'type', PropertyValue::OPERATOR_EQUALS, 'fellowship_member'],
                        ['property', 'race', PropertyValue::OPERATOR_NOT, 'human'],
                        ['property', 'name', PropertyValue::OPERATOR_IN, ['Frodo', 'Sam', 'Pippin', 'Merry']],
                        ['property', 'name', PropertyValue::OPERATOR_IN, ['Gandalf', 'Gimli', 'Legolas', 'Aragorn']],
                        ['property', 'age', PropertyValue::OPERATOR_GREATER_THAN, 50],
                        ['property', 'height', PropertyValue::OPERATOR_GREATER_THAN_EQUAL, 160],
                        ['property', 'size', PropertyValue::OPERATOR_LOWER_THAN, 15],
                        ['property', 'length', PropertyValue::OPERATOR_LOWER_THAN_EQUAL, 23]
                    ]
                ]
            ],
            [ // #3 - nested criteria
                'criteria' => [
                    'type' => 'fellowship_member',
                    KnitOptions::LOGIC_OR => [
                        'race' => ['hobbit', 'dwarf'],
                        'height:lte' => 140
                    ]
                ],
                'expected' => [
                    'logic' => KnitOptions::LOGIC_AND,
                    'properties' => [
                        ['property', 'type', PropertyValue::OPERATOR_EQUALS, 'fellowship_member'],
                        ['criteria', [
                            'logic' => KnitOptions::LOGIC_OR,
                            'properties' => [
                                ['property', 'race', PropertyValue::OPERATOR_IN, ['hobbit', 'dwarf']],
                                ['property', 'height', PropertyValue::OPERATOR_LOWER_THAN_EQUAL, 140]
                            ]
                        ]]
                    ]
                ]
            ],
        ];
    }
}
