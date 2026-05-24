<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamsIndexProps extends Data
{
	/**
	 * @param Team[] $teams
	 */
	public function __construct(
		public array $teams,
	) {}
}
