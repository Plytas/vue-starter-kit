<?php

use App\Data\WelcomeProps;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => Inertia::render('Welcome', new WelcomeProps(
	canRegister: (bool) config('auth.registration_enabled'),
)))->name('home');

Route::prefix('{current_team}')
	->middleware(['auth', 'verified', EnsureTeamMembership::class])
	->group(function () {
		Route::inertia('dashboard', 'Dashboard')->name('dashboard');
	});

Route::middleware(['auth'])->group(function () {
	Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
