<?php
/**
 * Exception thrown when trying to fetch an extension that hasn't been defined.
 * 
 * @package Knit
 * @subpackage Exceptions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Exceptions;

use MD\Foundation\Exceptions\NotFoundException;

class ExtensionNotDefinedException extends NotFoundException
{



}