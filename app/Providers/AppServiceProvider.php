<?php

namespace App\Providers;

use App\Http\Controllers\Auth\AuthenticateUsingPasskeyController;
use Illuminate\Support\ServiceProvider;
use Override;
use Spatie\LaravelPasskeys\Http\Controllers\AuthenticateUsingPasskeyController as BasePasskeyController;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	#[Override]
	public function register(): void
	{
		$this->app->bind(BasePasskeyController::class, AuthenticateUsingPasskeyController::class);
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		//
	}
}
