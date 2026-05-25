<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Contracts\Support\Responsable;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailPromptResponse implements Responsable
{
	use RedirectsToCurrentTeam;

	public function __construct(public string $name) {}

	public function toResponse($request): Response
	{
		return redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects($this->name)));
	}
}
