<?php

namespace App\Orders;

use RuntimeException;

/**
 * A piece on an order cannot be held again: it has sold out or been withdrawn.
 */
class PieceNoLongerAvailable extends RuntimeException
{
    public function __construct(public readonly string $piece)
    {
        parent::__construct("{$piece} is no longer available.");
    }
}
