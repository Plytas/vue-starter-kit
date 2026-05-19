<?php

namespace App\Actions\Fortify;

use App\Data\RegisterRequest;
use App\Models\User;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
	/**
	 * @param array<string, string> $input
	 */
	public function create(array $input): User
	{
		$data = RegisterRequest::validateAndCreate($input);

		return User::query()->create([
			'name' => $data->name,
			'email' => $data->email,
			'password' => $data->password,
		]);
	}
}
