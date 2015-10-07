<?php
namespace Knit;

/**
 * Container for various Knit constants.
 *
 * @package   Knit
 * @author    Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright 2015 Michał Pałys-Dudek
 * @license   https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
interface KnitOptions
{
    /**
     * Order constants.
     */
    const ORDER_ASC = 1;
    const ORDER_DESC = -1;

    /**
     * Logic constants.
     */
    const LOGIC_OR = '__OR__';
    const LOGIC_AND = '__AND__';

    /**
     * Join options.
     */
    //const EXCLUDE_EMPTY = '__EXCLUDE_EMPTY__';
}
