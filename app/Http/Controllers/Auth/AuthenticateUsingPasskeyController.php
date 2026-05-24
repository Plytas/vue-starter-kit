<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Http\Controllers\AuthenticateUsingPasskeyController as BaseController;

class AuthenticateUsingPasskeyController extends BaseController
{
    protected function validPasskeyResponse(Request $request): RedirectResponse
    {
        if (Session::has('passkeys.redirect')) {
            return redirect()->to(Session::pull('passkeys.redirect'));
        }

        $user = auth()->user();
        $team = $user !== null ? ($user->currentTeam ?? $user->personalTeam()) : null;

        if (! $team) {
            abort(403);
        }

        $url = Session::pull('url.intended', route('dashboard', ['current_team' => $team->slug], false));

        return redirect()->to($url);
    }
}
