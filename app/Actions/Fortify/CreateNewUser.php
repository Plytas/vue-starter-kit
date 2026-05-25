<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Data\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
	/**
	 * @param array<string, string> $input
	 */
	public function create(array $input): User
	{
		$data = RegisterRequest::validateAndCreate($input);

		return DB::transaction(function () use ($data) {
			$user = User::query()->create([
				'name' => $data->name,
				'email' => $data->email,
				'password' => $data->password,
			]);

			app(CreateTeam::class)->handle($user, $user->name . "'s Team", isPersonal: true);

			return $user;
		});
	}
}
