<?php

namespace App\Data;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RegisterRequest extends Data
{
	use PasswordValidationRules;
	use ProfileValidationRules;

	public function __construct(
		public string $name,
		public string $email,
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
			...static::profileRules(),
			'password' => static::passwordRules(),
		];
	}
}
