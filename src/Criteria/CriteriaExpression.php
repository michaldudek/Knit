<?php
namespace Knit\Criteria;

use Knit\Criteria\PropertyValue;
use Knit\KnitOptions;

/**
 * Criteria expression class.
 *
 * @package    Knit
 * @subpackage Criteria
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
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
    protected $criteria = [];

    /**
     * Constructor.
     *
     * @param array $criteria [optional] Criteria array that will be converted into proper expressions.
     * @param string $logic [optional] Logic that joins the criteria. One of `KnitOptions::LOGIC_*` constants.
     */
    public function __construct(array $criteria = [], $logic = null)
    {
        // parse the logic - AND is default and only allow for AND or OR - throw exceptions on anything else
        $logic = ($logic === null) ? KnitOptions::LOGIC_AND : $logic;

        if ($logic !== KnitOptions::LOGIC_AND && $logic !== KnitOptions::LOGIC_OR) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unrecognized logical operator passed in criteria. You can only use "Knit\KnitOptions::LOGIC_AND"'
                    .' or "Knit\KnitOptions::LOGIC_OR", "%s" given.',
                    $logic
                )
            );
        }

        $this->logic = $logic;

        // now parse the given criteria rows
        foreach ($criteria as $key => $value) {
            // if key is a logical operator then we have a subexpression
            if ($key === KnitOptions::LOGIC_AND || $key === KnitOptions::LOGIC_OR) {
                $this->criteria[] = new CriteriaExpression($value, $key);
                continue;
            }

            $this->criteria[] = new PropertyValue($key, $value);
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
    public function getLogic()
    {
        return $this->logic;
    }

    /**
     * Returns the list of criteria in this expression.
     *
     * @return array
     */
    public function getCriteria()
    {
        return $this->criteria;
    }
}
