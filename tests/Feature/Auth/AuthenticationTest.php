<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function (): void {
	$response = $this->get(route('login'));

	$response->assertOk();
});

test('users can authenticate using the login screen', function (): void {
	$user = User::factory()->withoutTwoFactor()->create();

	$response = $this->post(route('login.store'), [
		'email' => $user->email,
		'password' => 'password',
	]);

	$this->assertAuthenticated();
	$response->assertRedirect(route('dashboard', ['current_team' => $user->fresh()->currentTeam->slug]));
});

test('users with two factor enabled are redirected to two factor challenge', function (): void {
	$this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

	Features::twoFactorAuthentication([
		'confirm' => true,
		'confirmPassword' => true,
	]);

	$user = User::factory()->create();

	$response = $this->post(route('login.store'), [
		'email' => $user->email,
		'password' => 'password',
	]);

	$response->assertRedirect(route('two-factor.login'));
	$response->assertSessionHas('login.id', $user->id);
	$this->assertGuest();
});

test('users can not authenticate with invalid password', function (): void {
	$user = User::factory()->withoutTwoFactor()->create();

	$this->post(route('login.store'), [
		'email' => $user->email,
		'password' => 'wrong-password',
	]);

	$this->assertGuest();
});

test('users can logout', function (): void {
	$user = User::factory()->withoutTwoFactor()->create();

	$response = $this->actingAs($user)->post(route('logout'));

	$response->assertRedirect(route('home'));
	$this->assertGuest();
});

test('users are rate limited', function (): void {
	$user = User::factory()->withoutTwoFactor()->create();

	RateLimiter::increment(implode('|', [$user->email, '127.0.0.1']), amount: 10);

	$response = $this->post(route('login.store'), [
		'email' => $user->email,
		'password' => 'wrong-password',
	]);

	$response->assertSessionHasErrors('email');

	$errors = session('errors');

	expect($errors->first('email'))->toContain('Too many login attempts');
});
