<?php

namespace App\Data\Teams;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Rules\ValidTeamInvitation;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

class AcceptTeamInvitationRequest extends Data
{
	public function __construct() {}

	public static function validateRoute(TeamInvitation $invitation, ?User $user): void
	{
		$rule = new ValidTeamInvitation($user);
		$fail = function (string $message) {
			throw ValidationException::withMessages(['invitation' => $message]);
		};

		$rule->validate('invitation', $invitation, $fail);
	}
}
