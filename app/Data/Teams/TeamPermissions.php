<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamPermissions extends Data
{
	public function __construct(
		public bool $canUpdateTeam,
		public bool $canDeleteTeam,
		public bool $canAddMember,
		public bool $canUpdateMember,
		public bool $canRemoveMember,
		public bool $canCreateInvitation,
		public bool $canCancelInvitation,
	) {}
}
