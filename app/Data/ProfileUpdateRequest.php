<?php

namespace App\Data;

use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProfileUpdateRequest extends Data
{
	use ProfileValidationRules;

	public function __construct(
		public string $name,
		public string $email,
	)
	{
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function rules(ValidationContext $context): array
	{
		$userId = Auth::id();

		return static::profileRules($userId !== null ? (int) $userId : null);
	}
}
