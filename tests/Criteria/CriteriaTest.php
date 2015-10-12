<?php
namespace Knit\Tests\Criteria;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\PropertyValue;
use Knit\Knit;

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
                    'logic' => Knit::LOGIC_AND,
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
                    'logic' => Knit::LOGIC_AND,
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
                    'name:not' => ['Dumbledore', 'Harry'],
                    'name:in' => ['Frodo', 'Sam', 'Pippin', 'Merry'],
                    'name' => ['Gandalf', 'Gimli', 'Legolas', 'Aragorn'],
                    'name:not_in' => ['Hermione', 'Ron'],
                    'name:like' => '%Baggins%',
                    'name:not_like' => '%Sackville-%',
                    'age:gt' => 50,
                    'height:gte' => 160,
                    'size:lt' => 15,
                    'length:lte' => 23,
                    'size:exists' => true,
                ],
                'expected' => [
                    'logic' => Knit::LOGIC_AND,
                    'properties' => [
                        ['property', 'type', PropertyValue::OPERATOR_EQUALS, 'fellowship_member'],
                        ['property', 'race', PropertyValue::OPERATOR_NOT, 'human'],
                        ['property', 'name', PropertyValue::OPERATOR_NOT, ['Dumbledore', 'Harry']],
                        ['property', 'name', PropertyValue::OPERATOR_IN, ['Frodo', 'Sam', 'Pippin', 'Merry']],
                        ['property', 'name', PropertyValue::OPERATOR_EQUALS, ['Gandalf','Gimli','Legolas','Aragorn']],
                        ['property', 'name', PropertyValue::OPERATOR_NOT_IN, ['Hermione', 'Ron']],
                        ['property', 'name', PropertyValue::OPERATOR_LIKE, '%Baggins%'],
                        ['property', 'name', PropertyValue::OPERATOR_NOT_LIKE, '%Sackville-%'],
                        ['property', 'age', PropertyValue::OPERATOR_GREATER_THAN, 50],
                        ['property', 'height', PropertyValue::OPERATOR_GREATER_THAN_EQUAL, 160],
                        ['property', 'size', PropertyValue::OPERATOR_LOWER_THAN, 15],
                        ['property', 'length', PropertyValue::OPERATOR_LOWER_THAN_EQUAL, 23],
                        ['property', 'size', PropertyValue::OPERATOR_EXISTS, true]
                    ]
                ]
            ],
            [ // #3 - nested criteria
                'criteria' => [
                    'type' => 'fellowship_member',
                    Knit::LOGIC_OR => [
                        'race' => ['hobbit', 'dwarf'],
                        'height:lte' => 140
                    ]
                ],
                'expected' => [
                    'logic' => Knit::LOGIC_AND,
                    'properties' => [
                        ['property', 'type', PropertyValue::OPERATOR_EQUALS, 'fellowship_member'],
                        ['criteria', [
                            'logic' => Knit::LOGIC_OR,
                            'properties' => [
                                ['property', 'race', PropertyValue::OPERATOR_EQUALS, ['hobbit', 'dwarf']],
                                ['property', 'height', PropertyValue::OPERATOR_LOWER_THAN_EQUAL, 140]
                            ]
                        ]]
                    ]
                ]
            ],
            [ // #4 - nested criteria subset
                'criteria' => [
                    'type' => 'fellowship_member',
                    Knit::LOGIC_OR => [
                        ['race' => 'hobbit', 'name' => 'Frodo'],
                        ['race' => 'elf', 'name' => 'Legolas'],
                        ['race' => 'dwarf', 'name' => 'Gimli']
                    ]
                ],
                'expected' => [
                    'logic' => Knit::LOGIC_AND,
                    'properties' => [
                        ['property', 'type', PropertyValue::OPERATOR_EQUALS, 'fellowship_member'],
                        ['criteria', [
                            'logic' => Knit::LOGIC_OR,
                            'properties' => [
                                ['criteria', [
                                    'logic' => Knit::LOGIC_AND,
                                    'properties' => [
                                        ['property', 'race', PropertyValue::OPERATOR_EQUALS, 'hobbit'],
                                        ['property', 'name', PropertyValue::OPERATOR_EQUALS, 'Frodo']
                                    ]
                                ]],
                                ['criteria', [
                                    'logic' => Knit::LOGIC_AND,
                                    'properties' => [
                                        ['property', 'race', PropertyValue::OPERATOR_EQUALS, 'elf'],
                                        ['property', 'name', PropertyValue::OPERATOR_EQUALS, 'Legolas']
                                    ]
                                ]],
                                ['criteria', [
                                    'logic' => Knit::LOGIC_AND,
                                    'properties' => [
                                        ['property', 'race', PropertyValue::OPERATOR_EQUALS, 'dwarf'],
                                        ['property', 'name', PropertyValue::OPERATOR_EQUALS, 'Gimli']
                                    ]
                                ]],
                            ]
                        ]]
                    ]
                ]
            ]
        ];
    }
}
