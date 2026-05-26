<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * @return array<int, mixed>
     */
    protected static function passwordRules(): array
    {
        return ['required', 'confirmed', Password::defaults()];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function currentPasswordRules(): array
    {
        return ['required', 'current_password'];
    }
}
