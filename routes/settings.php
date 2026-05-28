<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
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

		Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

		Route::put('settings/password', [SecurityController::class, 'update'])
			->middleware('throttle:6,1')
			->name('security.update');

		Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
	});
});
