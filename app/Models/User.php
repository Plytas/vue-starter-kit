<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsUri;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
	/** @use HasFactory<UserFactory> */
	use HasFactory;

	use Notifiable;
	/* @chisel-passkeys */
	use PasskeyAuthenticatable;
	/* @end-chisel-passkeys */
	/* @chisel-2fa */
	use TwoFactorAuthenticatable;
	/* @end-chisel-2fa */

	/**
	 * @return array{avatar: 'Illuminate\Database\Eloquent\Casts\AsUri', email_verified_at: 'immutable_datetime', password: 'hashed', created_at: 'immutable_datetime', two_factor_confirmed_at: 'immutable_datetime'}
	 */
	protected function casts(): array
	{
		return [
			'avatar' => AsUri::class,
			'email_verified_at' => 'immutable_datetime',
			'password' => 'hashed',
			'created_at' => 'immutable_datetime',
			'two_factor_confirmed_at' => 'immutable_datetime',
		];
	}
}
