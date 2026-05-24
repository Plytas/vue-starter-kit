<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamEditProps extends Data
{
	/**
	 * @param TeamMember[] $members
	 * @param TeamInvitation[] $invitations
	 * @param RoleOption[] $availableRoles
	 */
	public function __construct(
		public Team $team,
		public array $members,
		public array $invitations,
		public TeamPermissions $permissions,
		public array $availableRoles,
	) {}
}
