<?php

namespace App\Demo;

use RuntimeException;

/**
 * A demonstration account tried to change something customers see.
 */
class DemoAccountCannotChange extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Demonstration accounts can look at everything, but cannot change what customers see: products, prices, photos, pages, delivery fees and store settings. Orders and stock are yours to work.');
    }
}
