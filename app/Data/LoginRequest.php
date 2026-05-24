<?php

namespace App\Data;

use App\Models\User as UserModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LoginRequest extends Data
{
	public function __construct(
		public string $email,
		public string $password,
		public bool   $remember = false,
	) {}

	public function validateCredentials(): UserModel
	{
		$this->ensureIsNotRateLimited();

		/** @var UserModel|null $user */
		$user = Auth::getProvider()->retrieveByCredentials(['email' => $this->email, 'password' => $this->password]);

		if (!$user || !Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
			RateLimiter::hit($this->throttleKey());

			throw ValidationException::withMessages([
				'email' => trans('auth.failed'),
			]);
		}

		RateLimiter::clear($this->throttleKey());

		return $user;
	}

	public function ensureIsNotRateLimited(): void
	{
		if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
			return;
		}

		$seconds = RateLimiter::availableIn($this->throttleKey());

		throw ValidationException::withMessages([
			'email' => trans('auth.throttle', [
				'seconds' => $seconds,
				'minutes' => ceil($seconds / 60),
			]),
		]);
	}

	public function throttleKey(): string
	{
		return Str::of($this->email)
			->lower()
			->append('|' . Request::ip())
			->transliterate()
			->value();
	}
}
