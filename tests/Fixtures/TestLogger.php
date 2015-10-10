<?php
namespace Knit\Tests\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * TestLogger
 *
 * @package    Knit
 * @subpackage Tests
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class TestLogger extends AbstractLogger
{
    /**
     * Last logger message.
     *
     * @var string|null
     */
    protected $lastMessage;

    /**
     * Last logger context.
     *
     * @var array
     */
    protected $lastContext = [];

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level Log level.
     * @param string $message Log message.
     * @param array $context Message context.
     */
    public function log($level, $message, array $context = [])
    {
        $this->lastMessage = $message;
        $this->lastContext = $context;
    }

    /**
     * Returns the last log message.
     *
     * @return string|null
     */
    public function getLastMessage()
    {
        return $this->lastMessage;
    }

    /**
     * Returns the last log context.
     *
     * @return array
     */
    public function getLastContext()
    {
        return $this->lastContext;
    }
}
