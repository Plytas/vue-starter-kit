<?php

namespace App\Http\Controllers\Teams;

use App\Data\Teams\UpdateTeamMemberRequest;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeamMemberController
{
	/**
	 * Update the specified team member's role.
	 */
	public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
	{
		Gate::authorize('updateMember', $team);

		$newRole = TeamRole::from($request->role);

		$team->memberships()
			->where('user_id', $user->id)
			->firstOrFail()
			->update(['role' => $newRole]);

		return to_route('teams.edit', ['team' => $team->slug]);
	}

	/**
	 * Remove the specified team member.
	 */
	public function destroy(Team $team, User $user): RedirectResponse
	{
		Gate::authorize('removeMember', $team);

		abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

		$team->memberships()
			->where('user_id', $user->id)
			->delete();

		if ($user->isCurrentTeam($team)) {
			$user->switchTeam($user->personalTeam());
		}

		return to_route('teams.edit', ['team' => $team->slug]);
	}
}
