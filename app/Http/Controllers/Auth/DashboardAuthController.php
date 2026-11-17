<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DashboardAuthController extends Controller
{
    public function showLogin(Request $request): RedirectResponse|View
    {
        if ($request->session()->get('dashboard_authenticated') === true) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function attemptLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $configuredUsername = config('dashboard_auth.username');
        $configuredPasswordHash = config('dashboard_auth.password_hash');

        if (! is_string($configuredUsername) || $configuredUsername === '' || ! is_string($configuredPasswordHash) || $configuredPasswordHash === '') {
            return back()
                ->withErrors(['username' => 'Authentification non configuree. Contactez l administrateur.'])
                ->onlyInput('username');
        }

        $isValidUser = hash_equals($configuredUsername, $credentials['username'])
            && Hash::check($credentials['password'], $configuredPasswordHash);

        if (! $isValidUser) {
            return back()
                ->withErrors(['username' => 'Identifiants invalides.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put('dashboard_authenticated', true);
        $request->session()->put('dashboard_username', $credentials['username']);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
