<?php
/**
 * Exception usually thrown when trying to redefine a structure.
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

class StructureDefinedException extends ReadOnlyException
{



}