<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WelcomeProps extends Data
{
    public function __construct(
        public bool $canRegister,
    ) {}
}
