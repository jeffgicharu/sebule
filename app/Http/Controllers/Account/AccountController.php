<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * A customer's own corner of the shop: their orders and their details.
 */
class AccountController extends Controller
{
    /** The next visitor has to find the demonstration account as this one did. */
    private const DEMO = 'This is a demonstration account, so its name, email and password stay as they are. Its addresses and orders are yours to try.';

    /** The orders placed from this account, newest first. */
    public function show(Request $request): View
    {
        return view('shop.account.orders', [
            'customer' => $this->customer(),
            'orders' => $this->customer()->orders()->withSum('items as pieces', 'quantity')->paginate(10),
        ]);
    }

    public function details(): View
    {
        return view('shop.account.details', ['customer' => $this->customer()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        if ($customer->is_demo) {
            return back()->with('shop.problem', self::DEMO);
        }
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (Phone::normalise((string) $value) === null) {
                    $fail('Enter a Kenyan mobile number, such as 0712 345 678, or leave it empty.');
                }
            }],
            'marketing' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Tell us your name.',
            'name.min' => 'Tell us your name.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'That email address does not look right.',
            'email.unique' => 'Another account already uses that email address.',
        ]);

        $customer->update([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => filled($data['phone'] ?? null) ? Phone::normalise($data['phone']) : null,
            'marketing_opt_in' => (bool) ($data['marketing'] ?? false),
        ]);

        return back()->with('shop.notice', 'Your details are saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        if ($customer->is_demo) {
            return back()->with('shop.problem', self::DEMO);
        }

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($customer): void {
                if (! Hash::check((string) $value, $customer->password)) {
                    $fail('That is not your current password.');
                }
            }],
            'password' => ['required', 'string', Password::min(8), 'max:200'],
        ], [
            'current_password.required' => 'Enter your current password.',
            'password.required' => 'Choose a new password.',
            'password.min' => 'Use at least 8 characters for your password.',
        ]);

        $customer->update(['password' => $data['password']]);

        // Signs this account out everywhere else, and keeps this browser signed in.
        Auth::guard('customer')->logoutOtherDevices($data['password']);

        return back()->with('shop.notice', 'Your password is changed.');
    }

    private function customer(): Customer
    {
        return Auth::guard('customer')->user();
    }
}
