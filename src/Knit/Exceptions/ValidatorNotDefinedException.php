<?php
/**
 * Exception thrown when trying to fetch a validator that hasn't been defined.
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

class ValidatorNotDefinedException extends NotFoundException
{



}