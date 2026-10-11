<?php

namespace App\Models;

use App\Messages\TellAccountHolder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A customer's account. Staff are users and sign in to the back office; a
 * customer signs in to the shop and to nothing else.
 */
#[Fillable(['name', 'email', 'phone', 'password', 'marketing_opt_in', 'is_demo'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'marketing_opt_in' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderByDesc('id');
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('id');
    }

    /** @return HasOne<Cart, $this> */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** The link to choose a new password goes out as one of the store's own emails, so it is logged and rationed like the rest. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        app(TellAccountHolder::class)->passwordReset($this, $token);
    }

    public function defaultAddress(): ?CustomerAddress
    {
        return $this->addresses->first();
    }
}
