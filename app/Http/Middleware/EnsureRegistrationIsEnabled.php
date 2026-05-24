<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsEnabled
{
	/**
	 * @param Closure(Request): Response $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
		if ($request->route()?->named('register', 'register.store') && !config('auth.registration_enabled')) {
			abort(404);
		}

		return $next($request);
	}
}
