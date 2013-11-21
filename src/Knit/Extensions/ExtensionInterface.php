<?php
/**
 * Interface that should be implemented by all Knit Repository extensions.
 * 
 * @package Knit
 * @subpackage Extensions
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Extensions;

use Knit\Entity\Repository;

interface ExtensionInterface
{

    /**
     * Adds the extension to the given repository.
     * 
     * @param Repository $repository Repository to which to add the extension.
     */
    public function addExtension(Repository $repository);

}