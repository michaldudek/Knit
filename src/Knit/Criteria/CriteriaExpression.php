<?php
/**
 * Criteria expression class.
 * 
 * @package Knit
 * @subpackage Criteria
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Criteria;

use Knit\Criteria\FieldValue;

class CriteriaExpression
{

    const LOGIC_AND = '__AND__';
    const LOGIC_OR = '__OR__';

    /**
     * Logic that joins all the criteria in this expression.
     * 
     * @var string
     */
    protected $logic;

    /**
     * List of criteria in this expression.
     * 
     * @var array
     */
    protected $criteria = array();

    /**
     * Constructor.
     * 
     * @param array $criteria [optional] Array of criteria that need to be converted into proper expressions.
     */
    public function __construct(array $criteria = array(), $logic = null) {
        // parse the logic - AND is default and only allow for AND or OR - throw exceptions on anything else
        $logic = ($logic === null) ? self::LOGIC_AND : $logic;

        if ($logic !== self::LOGIC_AND && $logic !== self::LOGIC_OR) {
            throw new \InvalidArgumentException('Unrecognized logical operator passed to '. get_called_class() .' constructor. You can only use "Knit\Knit::LOGIC_AND" or "Knit\Knit::LOGIC_OR", "'. $logic .'"" given.');
        }

        $this->logic = $logic;

        // now parse the given criteria rows
        foreach($criteria as $key => $value) {
            // if key is a logical operator then we have a subexpression
            if ($key === self::LOGIC_AND || $key === self::LOGIC_OR) {
                $this->criteria[] = new CriteriaExpression($value, $key);
                continue;
            }

            $this->criteria[] = new FieldValue($key, $value);
        }
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns the logic that joins all the criteria in this expression.
     * 
     * @return string
     */
    public function getLogic() {
        return $this->logic;
    }

    /**
     * Returns the list of criteria in this expression.
     * 
     * @return array
     */
    public function getCriteria() {
        return $this->criteria;
    }

}