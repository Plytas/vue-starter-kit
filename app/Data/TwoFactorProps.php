<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TwoFactorProps extends Data
{
    public function __construct(
        public bool $twoFactorEnabled,
        public bool $requiresConfirmation,
    ) {}
}
