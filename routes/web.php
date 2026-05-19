<?php

use App\Data\WelcomeProps;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', fn() => Inertia::render('Welcome', new WelcomeProps(
	canRegister: Features::enabled(Features::registration()),
)))->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
	Route::get('dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
