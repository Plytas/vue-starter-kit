<?php

namespace App\Http\Controllers\Settings;

use App\Data\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController implements HasMiddleware
{
	public static function middleware(): array
	{
		return Features::canManageTwoFactorAuthentication()
			&& Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
				? [new Middleware('password.confirm', only: ['edit'])]
				: [];
	}

	public function edit(TwoFactorAuthenticationRequest $request): Response
	{
		$props = [
			'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
		];

		if (Features::canManageTwoFactorAuthentication()) {
			$request->ensureStateIsValid();

			$props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
			$props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
		}

		return Inertia::render('settings/Security', $props);
	}

	public function update(PasswordUpdateRequest $request): RedirectResponse
	{
		Auth::user()->update([
			'password' => Hash::make($request->password),
		]);

		Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

		return back();
	}
}
