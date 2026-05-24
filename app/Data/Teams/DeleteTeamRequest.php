<?php

namespace App\Data\Teams;

use Spatie\LaravelData\Data;

class DeleteTeamRequest extends Data
{
	public function __construct(
		public string $name = '',
	) {}
}
