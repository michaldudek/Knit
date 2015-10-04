<?php
/**
 * Exception usually thrown when there was no store defined.
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

class NoStoreException extends NotFoundException
{



}