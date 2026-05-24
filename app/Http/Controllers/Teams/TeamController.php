<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Data\Teams\DeleteTeamRequest;
use App\Data\Teams\RoleOption;
use App\Data\Teams\SaveTeamRequest;
use App\Data\Teams\Team as TeamData;
use App\Data\Teams\TeamEditProps;
use App\Data\Teams\TeamInvitation as TeamInvitationData;
use App\Data\Teams\TeamMember;
use App\Data\Teams\TeamsIndexProps;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController
{
    /**
     * Display a listing of the user's teams.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('teams/Index', new TeamsIndexProps(
            teams: $user->toUserTeams(includeCurrent: true)->all(),
        ));
    }

    /**
     * Store a newly created team.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $team = $createTeam->handle($user, $request->name);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team): Response
    {
        $user = $request->user();

        return Inertia::render('teams/Edit', new TeamEditProps(
            team: new TeamData(
                id: $team->id,
                name: $team->name,
                slug: $team->slug,
                isPersonal: $team->is_personal,
                role: null,
                roleLabel: null,
            ),
            members: $team->members()->get()->map(function ($member) {
                /** @var User $member */
                /** @var \App\Models\Membership $pivot */
                $pivot = $member->pivot; // @phpstan-ignore-line property.notFound
                /** @var \App\Enums\TeamRole $role */
                $role = $pivot->role;

                return new TeamMember(
                    id: $member->id,
                    name: $member->name,
                    email: $member->email,
                    avatar: $member->avatar ?? null,
                    role: $role->value,
                    roleLabel: $role->label(),
                );
            })->all(),
            invitations: $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(function (TeamInvitation $invitation) {
                    /** @var \App\Enums\TeamRole $role */
                    $role = $invitation->role;

                    return new TeamInvitationData(
                        code: $invitation->code,
                        email: $invitation->email,
                        role: $role->value,
                        roleLabel: $role->label(),
                        createdAt: $invitation->created_at->toISOString(),
                    );
                })->all(),
            permissions: $user->toTeamPermissions($team),
            availableRoles: array_map(
                fn (array $r) => new RoleOption(value: $r['value'], label: $r['label']),
                TeamRole::assignable(),
            ),
        ));
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $request->name]);

            return $team;
        });

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->belongsToTeam($team), 403);

        $request->user()->switchTeam($team);

        return back();
    }

    /**
     * Delete the specified team.
     */
    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('delete', $team);

        if ($request->name !== $team->name) {
            throw ValidationException::withMessages(['name' => __('The team name does not match.')]);
        }

        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team) {
            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam($affectedUser->personalTeam()));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        return to_route('teams.index');
    }
}
