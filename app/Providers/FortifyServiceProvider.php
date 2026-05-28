<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Data\ForgotPasswordProps;
use App\Data\RegisterProps;
use App\Data\ResetPasswordProps;
use App\Data\VerifyEmailPrompts;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		Fortify::createUsersUsing(CreateNewUser::class);
		Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

		Fortify::registerView(fn() => Inertia::render('auth/Register', new RegisterProps(
			passwordRules: Password::defaults()->toPasswordRulesString(),
		)));

		Fortify::requestPasswordResetLinkView(fn(Request $request) => Inertia::render('auth/ForgotPassword', new ForgotPasswordProps(
			status: $request->session()->get('status'),
		)));

		Fortify::resetPasswordView(fn(Request $request) => Inertia::render('auth/ResetPassword', new ResetPasswordProps(
			email: (string) $request->query('email', ''),
			token: (string) $request->route('token'),
			passwordRules: Password::defaults()->toPasswordRulesString(),
		)));

		Fortify::verifyEmailView(fn(Request $request) => Inertia::render('auth/VerifyEmail', new VerifyEmailPrompts(
			status: $request->session()->get('status'),
		)));

		Fortify::twoFactorChallengeView(fn() => Inertia::render('auth/TwoFactorChallenge'));

		Fortify::confirmPasswordView(fn() => Inertia::render('auth/ConfirmPassword'));

		RateLimiter::for('two-factor', function (Request $request) {
			return Limit::perMinute(5)->by($request->session()->get('login.id'));
		});

		RateLimiter::for('passkeys', function (Request $request) {
			return Limit::perMinute(10)->by(
				$request->session()->getId() . '|' . $request->ip(),
			);
		});
	}
}
