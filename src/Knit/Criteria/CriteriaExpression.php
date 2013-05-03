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
use Knit\KnitOptions;

class CriteriaExpression
{

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
        $logic = ($logic === null) ? KnitOptions::LOGIC_AND : $logic;

        if ($logic !== KnitOptions::LOGIC_AND && $logic !== KnitOptions::LOGIC_OR) {
            throw new \InvalidArgumentException('Unrecognized logical operator passed to '. get_called_class() .' constructor. You can only use "Knit\KnitOptions::LOGIC_AND" or "Knit\KnitOptions::LOGIC_OR", "'. $logic .'"" given.');
        }

        $this->logic = $logic;

        // now parse the given criteria rows
        foreach($criteria as $key => $value) {
            // if key is a logical operator then we have a subexpression
            if ($key === KnitOptions::LOGIC_AND || $key === KnitOptions::LOGIC_OR) {
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