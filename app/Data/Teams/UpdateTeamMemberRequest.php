<?php

namespace App\Data\Teams;

use App\Enums\TeamRole;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class UpdateTeamMemberRequest extends Data
{
	public function __construct(
		public string $role,
	) {}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function rules(ValidationContext $context): array
	{
		return [
			'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
		];
	}
}
