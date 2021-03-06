<?php
namespace Knit\Criteria;

use Knit\Criteria\PropertyValue;
use Knit\Knit;

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
     * Raw criteria based on which this object was created.
     *
     * @var array
     */
    protected $raw = [];

    /**
     * Constructor.
     *
     * @param array $criteria [optional] Criteria array that will be converted into proper expressions.
     * @param string $logic [optional] Logic that joins the criteria. One of `Knit::LOGIC_*` constants.
     */
    public function __construct(array $criteria = [], $logic = null)
    {
        // parse the logic - AND is default and only allow for AND or OR - throw exceptions on anything else
        $logic = ($logic === null) ? Knit::LOGIC_AND : $logic;

        if ($logic !== Knit::LOGIC_AND && $logic !== Knit::LOGIC_OR) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unrecognized logical operator passed in criteria. You can only use "Knit\Knit::LOGIC_AND"'
                    .' or "Knit\Knit::LOGIC_OR", "%s" given.',
                    $logic
                )
            );
        }

        $this->logic = $logic;
        $this->raw = $criteria;

        // now parse the given criteria rows
        foreach ($criteria as $key => $value) {
            // if key is a logical operator then we have a subexpression
            if ($key === Knit::LOGIC_AND || $key === Knit::LOGIC_OR) {
                $this->criteria[] = new CriteriaExpression($value, $key);
                continue;
            }

            // if key is numeric then we have a subset
            if (is_numeric($key)) {
                $this->criteria[] = new CriteriaExpression($value, Knit::LOGIC_AND);
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

    /**
     * Returns the raw criteria based on which this object was created.
     *
     * @return array
     */
    public function getRaw()
    {
        return $this->raw;
    }
}
