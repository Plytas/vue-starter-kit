<?php

namespace App\Data;

use App\Concerns\PasswordValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PasswordUpdateRequest extends Data
{
	use PasswordValidationRules;

	public function __construct(
		public string $current_password,
		public string $password,
		public string $password_confirmation,
	)
	{
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function rules(ValidationContext $context): array
	{
		return [
			'current_password' => static::currentPasswordRules(),
			'password' => static::passwordRules(),
		];
	}
}
