<?php

namespace App\Data;

use App\Data\Teams\Team;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SharedProps extends Data
{
	public function __construct(
		public object          $errors,
		public string          $name,
		public SharedAuthProps $auth,
		public bool            $sidebarOpen,
		public ?Team           $currentTeam,
		/** @var Team[] */
		public array           $teams,
	)
	{
	}
}
