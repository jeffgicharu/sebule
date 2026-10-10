<?php

namespace App\Inventory;

use App\Models\ProductVariant;
use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Only {$available} of {$variant->sku} in stock, {$requested} requested.");
    }
}
