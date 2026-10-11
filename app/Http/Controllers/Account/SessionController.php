<?php

namespace App\Http\Controllers\Account;

use App\Cart\CartManager;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Signing in to an account and out of it.
 */
class SessionController extends Controller
{
    public function create(): View
    {
        return view('shop.account.login');
    }

    public function store(Request $request, CartManager $carts): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'That email address does not look right.',
            'password.required' => 'Enter your password.',
        ]);

        $signedIn = Auth::guard('customer')->attempt(
            ['email' => Str::lower($data['email']), 'password' => $data['password']],
            $request->boolean('remember'),
        );

        if (! $signedIn) {
            // The same words whether the address or the password was wrong, so nobody learns which addresses have accounts.
            return back()->withInput($request->only('email', 'remember', 'next'))
                ->withErrors(['email' => 'That email address and password do not match an account.']);
        }

        $request->session()->regenerate();
        $carts->signedIn(Auth::guard('customer')->user());

        return redirect()->to(self::next($request));
    }

    public function destroy(Request $request, CartManager $carts): RedirectResponse
    {
        $carts->signedOut();

        Auth::guard('customer')->logout();

        // Only what belongs to the customer is let go; a member of staff signed in to the back office in the same browser stays signed in.
        $request->session()->forget(['checkout.details', 'checkout.key']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('shop.notice', 'You are signed out.');
    }

    /** Where to go after signing in: back to checkout when that is where the customer came from, otherwise to the account. */
    public static function next(Request $request): string
    {
        return $request->input('next') === 'checkout' ? route('checkout.details') : route('account.show');
    }
}
