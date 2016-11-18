<?php
namespace Knit\Tests\Fixtures;

use MongoDB\Driver\Exception\Exception as MongoException;

/**
 * MongoException class as we need something that implements MongoException interface.
 *
 * @package    Knit
 * @subpackage Tests
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2016 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class MongoExceptionMock extends \Exception implements MongoException
{
}
