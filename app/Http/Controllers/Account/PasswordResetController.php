<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * A forgotten password: a link by email that lets its holder choose a new one.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('shop.account.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'That email address does not look right.',
        ]);

        Password::broker('customers')->sendResetLink(['email' => Str::lower($data['email'])]);

        // The same answer whether or not the address has an account.
        return back()->with('shop.notice', 'If that address has an account, a link to choose a new password is on its way to it. The link works for one hour.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('shop.account.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8), 'max:200'],
        ], [
            'password.required' => 'Choose a new password.',
            'password.min' => 'Use at least 8 characters for your password.',
        ]);

        $status = Password::broker('customers')->reset(
            ['email' => Str::lower($data['email']), 'password' => $data['password'], 'token' => $data['token']],
            function (Customer $customer, string $password): void {
                $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This link has expired or has already been used. Ask for a new one below.']);
        }

        return redirect()->route('account.login')->with('shop.notice', 'Your password is changed. Sign in with the new one.');
    }
}
