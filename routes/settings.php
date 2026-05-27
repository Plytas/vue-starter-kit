<?php

use App\Http\Controllers\Settings\PasskeyController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
	Route::redirect('settings', '/settings/profile');

	// Profile edit + update — intentionally NOT behind `verified` so users
	// with an unverified email can view their profile and update / re-verify
	// their email (PR #252 — "Skip verified middleware for email update").
	Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
	Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

	Route::middleware('verified')->group(function (): void {
		// Profile destroy stays verified — PR #252's scope is email update only.
		Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

		Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

		Route::put('settings/password', [PasswordController::class, 'update'])
			->middleware('throttle:6,1')
			->name('user-password.update');

		Route::get('settings/passkey', [PasskeyController::class, 'edit'])->name('passkey.edit');
		Route::get('settings/passkey/register-options', [PasskeyController::class, 'generatePasskeyOptions'])->name('passkey.register-options');
		Route::post('settings/passkey', [PasskeyController::class, 'store'])->name('passkey.store');
		Route::delete('settings/passkey/{passkey}', [PasskeyController::class, 'destroy'])->name('passkey.destroy');

		Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

		Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
			->name('two-factor.show');
	});
});
