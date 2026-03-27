<?php

use App\Http\Controllers\Settings\PasskeyController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

		Route::get('settings/appearance', fn() => Inertia::render('settings/Appearance'))->name('appearance');

		Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
			->name('two-factor.show');

		Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
		Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

		Route::middleware(EnsureTeamMembership::class)->group(function () {
			Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
			Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
			Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
			Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

			Route::patch('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
			Route::delete('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

			Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
			Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
		});
	});
});
