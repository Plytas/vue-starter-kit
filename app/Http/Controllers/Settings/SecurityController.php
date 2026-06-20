<?php

namespace App\Http\Controllers\Settings;

use App\Data\PasskeyView;
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
use Laravel\Passkeys\Passkey;

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
		$user = $request->user();

		/* @chisel-passkeys */
		$canManagePasskeys = Features::canManagePasskeys();
		/* @end-chisel-passkeys */

		$props = [
			/* @chisel-2fa */
			'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
			/* @end-chisel-2fa */
			/* @chisel-passkeys */
			'canManagePasskeys' => $canManagePasskeys,
			'passkeys' => $canManagePasskeys
				? $user->passkeys->map(fn(Passkey $passkey) => new PasskeyView(
					id: $passkey->id,
					name: $passkey->name,
					authenticator: $passkey->authenticator,
					created_at_diff: $passkey->created_at->diffForHumans(),
					last_used_at_diff: $passkey->last_used_at?->diffForHumans(),
				))->values()->toArray()
				: [],
			/* @end-chisel-passkeys */
		];

		/* @chisel-2fa */
		if (Features::canManageTwoFactorAuthentication()) {
			$request->ensureStateIsValid();

			$props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
			$props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
		}
		/* @end-chisel-2fa */

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
