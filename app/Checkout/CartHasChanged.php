<?php

namespace App\Checkout;

use RuntimeException;

/**
 * The cart can no longer be bought as it stood when the customer reviewed it:
 * something sold out, was withdrawn or changed price in the meantime.
 */
class CartHasChanged extends RuntimeException {}
