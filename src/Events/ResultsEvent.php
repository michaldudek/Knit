<?php
namespace Knit\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event dispatched with results from a store.
 *
 * The results can be altered.
 *
 * @package    Knit
 * @subpackage Events
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class ResultsEvent extends Event
{
    /**
     * Results.
     *
     * @var array
     */
    protected $results = [];

    /**
     * Constructor.
     *
     * @param array $results Results.
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Returns the results.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Sets new results.
     *
     * @param array $results Results.
     */
    public function setResults(array $results)
    {
        $this->results = $results;
    }
}
