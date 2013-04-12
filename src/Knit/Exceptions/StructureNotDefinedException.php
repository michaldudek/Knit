<?php
/**
 * Exception usually thrown when there was no structure defined for an entity.
 * 
 * @package Knit
 * @subpackage Exceptions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Exceptions;

use Splot\Foundation\Exceptions\ReadOnlyException;

class StructureNotDefinedException extends ReadOnlyException
{



}