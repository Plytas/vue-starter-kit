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
			passkeyStatus: $request->session()->get('authenticatePasskey::message'),
		));
	}

	public function store(LoginRequest $request): RedirectResponse
	{
		$user = $request->validateCredentials();

		if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
			Session::put([
				'login.id' => $user->getKey(),
				'login.remember' => $request->remember,
			]);

			return to_route('two-factor.login');
		}

		Auth::login($user, $request->remember);

		Session::regenerate();

		$team = $user->currentTeam ?? $user->personalTeam();

		if (!$team) {
			abort(403);
		}

		return redirect()->intended(route('dashboard', ['current_team' => $team->slug], false));
	}

	public function destroy(Request $request): RedirectResponse
	{
		Auth::guard('web')->logout();

		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return redirect('/');
	}
}
