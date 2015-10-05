<?php
namespace Knit\Exceptions;

/**
 * Usually thrown when there was an error executing a query in persistent store.
 *
 * @package    Knit
 * @subpackage Exceptions
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class StoreQueryErrorException extends \RuntimeException
{
}
