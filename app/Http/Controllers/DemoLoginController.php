<?php

namespace App\Http\Controllers;

use App\Cart\CartManager;
use App\Enums\StaffRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets a visitor into a demonstration account without a password. These
 * addresses exist only where the store is marked as a demonstration, and only
 * accounts marked as demonstration accounts can be entered through them.
 */
class DemoLoginController extends Controller
{
    /** Into the back office, as a store manager unless another role is asked for. */
    public function staff(Request $request, string $role = 'manager'): RedirectResponse
    {
        abort_unless(config('store.demo') && StaffRole::tryFrom($role) !== null, 404);

        $staff = User::query()->where('is_demo', true)->where('is_active', true)->where('role', $role)->first();

        abort_if($staff === null, 404);

        Auth::guard('web')->login($staff);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    /** Into the shop, as a customer with orders behind them. */
    public function customer(Request $request, CartManager $carts): RedirectResponse
    {
        abort_unless(config('store.demo'), 404);

        $customer = Customer::query()->where('is_demo', true)->first();

        abort_if($customer === null, 404);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $carts->signedIn($customer);

        return redirect()->route('account.show');
    }
}
