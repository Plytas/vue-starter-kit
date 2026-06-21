<?php

namespace App\Http\Controllers\Auth;

use App\Data\LoginProps;
use App\Data\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController
{
	public function create(Request $request): Response
	{
		return Inertia::render('auth/Login', new LoginProps(
			canResetPassword: Route::has('password.request'),
			canRegister: (bool) config('auth.registration_enabled'),
			status: $request->session()->get('status'),
		));
	}

	public function store(LoginRequest $request): RedirectResponse
	{
		$user = $request->validateCredentials();

		/* @chisel-2fa */
		if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
			Session::put([
				'login.id' => $user->getKey(),
				'login.remember' => $request->remember,
			]);

			return to_route('two-factor.login');
		}
		/* @end-chisel-2fa */

		Auth::login($user, $request->remember);

		Session::regenerate();

		return redirect()->intended(route('dashboard', absolute: false));
	}

	public function destroy(Request $request): RedirectResponse
	{
		Auth::guard('web')->logout();

		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return redirect('/');
	}
}
