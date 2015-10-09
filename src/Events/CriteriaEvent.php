<?php
namespace Knit\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event dispatched before accessing a store based on criteria.
 *
 * It makes possible to overwrite the search criteria and params.
 *
 * @package    Knit
 * @subpackage Events
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class CriteriaEvent extends Event
{
    /**
     * Search criteria.
     *
     * @var array
     */
    protected $criteria = [];

    /**
     * Search parameters.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Constructor.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search parameters.
     */
    public function __construct(array $criteria, array $params)
    {
        $this->criteria = $criteria;
        $this->params = $params;
    }

    /**
     * Returns the search criteria.
     *
     * @return array
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * Sets new search criteria.
     *
     * @param array $criteria Search criteria.
     */
    public function setCriteria(array $criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * Returns the search params.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Sets new search params.
     *
     * @param array $params Search params.
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }
}
