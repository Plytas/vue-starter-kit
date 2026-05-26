<?php

namespace App\Data;

use App\Concerns\PasswordValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DeleteUserRequest extends Data
{
	use PasswordValidationRules;

	public function __construct(
		public string $password,
	)
	{
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function rules(ValidationContext $context): array
	{
		return [
			'password' => static::currentPasswordRules(),
		];
	}
}
