<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * @return array<int, mixed>
     */
    protected static function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function emailRules(?int $userId = null): array
    {
        return ['required', 'string', 'lowercase', 'max:255', Rule::email(), Rule::unique(User::class)->ignore($userId)];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected static function profileRules(?int $userId = null): array
    {
        return ['name' => static::nameRules(), 'email' => static::emailRules($userId)];
    }
}
