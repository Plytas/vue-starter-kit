<?php

namespace App\Http\Controllers\Teams;

use App\Data\Teams\AcceptTeamInvitationRequest;
use App\Data\Teams\CreateTeamInvitationRequest;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class TeamInvitationController
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        /** @var User $authUser */
        $authUser = Auth::user();

        $invitation = $team->invitations()->create([
            'email' => $request->email,
            'role' => TeamRole::from($request->role),
            'invited_by' => $authUser->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->team_id === $team->id, 404);

        Gate::authorize('cancelInvitation', $team);

        $invitation->delete();

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        AcceptTeamInvitationRequest::validateRoute($invitation, $user);

        $team = $invitation->team;

        DB::transaction(function () use ($user, $invitation, $team) {
            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);
        });

        return to_route('dashboard', ['current_team' => $team->slug]);
    }
}
