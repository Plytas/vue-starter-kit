<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamInvitation extends Data
{
	public function __construct(
		public string $code,
		public string $email,
		public string $role,
		public string $roleLabel,
		public string $createdAt,
	) {}
}
