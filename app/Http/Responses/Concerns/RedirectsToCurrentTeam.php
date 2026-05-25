<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;

trait RedirectsToCurrentTeam
{
	protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
	{
		$team = $this->currentTeam($request);

		return "/{$team->slug}{$redirect}";
	}

	protected function currentTeam(Request $request): Team
	{
		$user = $request->user();
		$team = $user !== null ? ($user->currentTeam ?? $user->personalTeam()) : null;

		if (!$team) {
			abort(403);
		}

		return $team;
	}
}
