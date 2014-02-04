<?php
/**
 * Container for various Knit constants.
 * 
 * @package Knit
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit;

interface KnitOptions
{

    const TYPE_INT = 'int';
    const TYPE_STRING = 'string';
    const TYPE_FLOAT = 'float';
    const TYPE_ENUM = 'enum';
    const TYPE_ARRAY = 'array';

    const LOGIC_OR = '__OR__';
    const LOGIC_AND = '__AND__';

    const EXCLUDE_EMPTY = '__EXCLUDE_EMPTY__';

}