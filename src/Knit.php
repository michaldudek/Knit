<?php
namespace Knit;

/**
 * Main Knit class.
 *
 * @package   Knit
 * @author    Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright 2015 Michał Pałys-Dudek
 * @license   https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class Knit
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
    const EXCLUDE_EMPTY = '__EXCLUDE_EMPTY__';

    /**
     * Event names.
     */
    const EVENT_WILL_READ = 'knit.will_read';
    const EVENT_DID_READ = 'knit.did_read';
    const EVENT_WILL_ADD = 'knit.will_add';
    const EVENT_DID_ADD = 'knit.did_add';
    const EVENT_WILL_UPDATE = 'knit.will_update';
    const EVENT_DID_UPDATE = 'knit.did_update';
    const EVENT_WILL_SAVE = 'knit.will_save';
    const EVENT_DID_SAVE = 'knit.did_save';
    const EVENT_WILL_DELETE = 'knit.will_delete';
    const EVENT_DID_DELETE = 'knit.did_delete';
}
