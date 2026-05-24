<?php

namespace App\Data\Teams;

use App\Enums\TeamRole;
use App\Rules\UniqueTeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateTeamInvitationRequest extends Data
{
	public function __construct(
		public string $email,
		public string $role,
	) {}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function rules(ValidationContext $context): array
	{
		/** @var Request $request */
		$request = app(Request::class);
		/** @var \App\Models\Team $team */
		$team = $request->route('team');

		return [
			'email' => ['required', 'string', 'email', 'max:255', new UniqueTeamInvitation($team)],
			'role' => ['required', 'string', Rule::enum(TeamRole::class)],
		];
	}
}
