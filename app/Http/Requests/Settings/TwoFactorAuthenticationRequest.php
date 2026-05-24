<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Features;
use Laravel\Fortify\InteractsWithTwoFactorState;

/**
 * Deliberate FormRequest exception — this is the only FormRequest in this fork.
 *
 * Reason: hosts the Laravel\Fortify\InteractsWithTwoFactorState trait, which
 * requires FormRequest internals (session, user, request lifecycle) and
 * performs DB + session mutations owned by Fortify. Inlining these methods
 * into the controller would risk silent divergence on Fortify upgrades.
 *
 * See .starter-kit/adaptation-guide.md Pattern 1 — all other requests use
 * spatie/laravel-data Data classes in app/Data/.
 */
class TwoFactorAuthenticationRequest extends FormRequest
{
	use InteractsWithTwoFactorState;

	public function authorize(): bool
	{
		return Features::enabled(Features::twoFactorAuthentication());
	}

	/**
	 * @return array<string, ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [];
	}
}
