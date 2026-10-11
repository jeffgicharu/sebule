<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somebody who has ordered, as the store knows them: by phone number. Read
 * from a view over the orders, so it is always up to date and never written to.
 */
class Buyer extends Model
{
    protected $table = 'buyers';

    protected $primaryKey = 'phone';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
            'orders_placed' => 'integer',
            'orders_paid' => 'integer',
            'spent' => 'integer',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'phone', 'phone')->orderByDesc('id');
    }
}
