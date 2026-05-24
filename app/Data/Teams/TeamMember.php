<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamMember extends Data
{
	public function __construct(
		public int $id,
		public string $name,
		public string $email,
		public ?string $avatar,
		public string $role,
		public string $roleLabel,
	) {}
}
