<?php
/**
 * Exception usually thrown when trying to redefine a repository.
 * 
 * @package Knit
 * @subpackage Exceptions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Exceptions;

use MD\Foundation\Exceptions\ReadOnlyException;

class RepositoryDefinedException extends ReadOnlyException
{



}