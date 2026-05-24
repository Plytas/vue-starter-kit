<?php

namespace App\Http\Controllers\Settings;

use App\Data\TwoFactorProps;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TwoFactorAuthenticationController implements HasMiddleware
{
	public static function middleware(): array
	{
		return Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
			? [new Middleware('password.confirm', only: ['show'])]
			: [];
	}

	public function show(TwoFactorAuthenticationRequest $request): Response
	{
		$request->ensureStateIsValid();

		return Inertia::render('settings/TwoFactor', new TwoFactorProps(
			twoFactorEnabled: $request->user()->hasEnabledTwoFactorAuthentication(),
			requiresConfirmation: Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
		));
	}
}
