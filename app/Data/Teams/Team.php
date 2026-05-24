<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Team extends Data
{
	public function __construct(
		public int $id,
		public string $name,
		public string $slug,
		public bool $isPersonal,
		public ?string $role,
		public ?string $roleLabel,
		public ?bool $isCurrent = null,
	) {}
}
