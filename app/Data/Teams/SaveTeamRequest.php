<?php

namespace App\Data\Teams;

use App\Rules\TeamName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class SaveTeamRequest extends Data
{
	public function __construct(
		public string $name,
	) {}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function rules(ValidationContext $context): array
	{
		return [
			'name' => ['required', 'string', 'max:255', new TeamName()],
		];
	}
}
