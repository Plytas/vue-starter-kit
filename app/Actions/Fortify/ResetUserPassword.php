<?php

namespace App\Actions\Fortify;

use App\Data\NewPasswordRequest;
use App\Models\User;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
	/**
	 * @param  array<string, string>  $input
	 */
	public function reset(User $user, array $input): void
	{
		$data = NewPasswordRequest::validateAndCreate($input);

		$user->forceFill([
			'password' => $data->password,
		])->save();
	}
}
