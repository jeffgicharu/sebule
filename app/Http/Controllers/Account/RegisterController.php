<?php

namespace App\Http\Controllers\Account;

use App\Cart\CartManager;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Opening an account. Nobody needs one to buy; it keeps addresses and orders
 * in one place for those who want that.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('shop.account.register');
    }

    public function store(Request $request, CartManager $carts): RedirectResponse
    {
        // Addresses are kept in lower case, so "Wanjiru@" and "wanjiru@" are one account.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (Phone::normalise((string) $value) === null) {
                    $fail('Enter a Kenyan mobile number, such as 0712 345 678, or leave it empty.');
                }
            }],
            'password' => ['required', 'string', Password::min(8), 'max:200'],
            'marketing' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Tell us your name.',
            'name.min' => 'Tell us your name.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'That email address does not look right.',
            'email.unique' => 'There is already an account with that email address. Sign in instead, or reset the password if you have forgotten it.',
            'password.required' => 'Choose a password.',
            'password.min' => 'Use at least 8 characters for your password.',
        ]);

        $customer = Customer::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => filled($data['phone'] ?? null) ? Phone::normalise($data['phone']) : null,
            'password' => $data['password'],
            'marketing_opt_in' => (bool) ($data['marketing'] ?? false),
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $carts->signedIn($customer);

        return redirect()->to(SessionController::next($request))
            ->with('shop.notice', 'Your account is open, '.Str::of($customer->name)->before(' ').'. Welcome to Sebule.');
    }
}
