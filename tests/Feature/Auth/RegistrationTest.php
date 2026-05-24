<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function (): void {
	$this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function (): void {
	$response = $this->get(route('register'));

	$response->assertStatus(200);
});

test('new users can register', function (): void {
	$response = $this->post(route('register.store'), [
		'name' => 'Test User',
		'email' => 'test@example.com',
		'password' => 'password',
		'password_confirmation' => 'password',
	]);

	$this->assertAuthenticated();
	$user = User::where('email', 'test@example.com')->first();
	$response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('registration can be disabled without removing routes', function (): void {
	config(['auth.registration_enabled' => false]);

	expect(Route::has('register'))->toBeTrue()
		->and(Route::has('register.store'))->toBeTrue();

	$this->get(route('home'))
		->assertOk()
		->assertInertia(fn (Assert $page) => $page
			->component('Welcome')
			->where('canRegister', false)
		);

	$this->get(route('login'))
		->assertOk()
		->assertInertia(fn (Assert $page) => $page
			->component('auth/Login')
			->where('canRegister', false)
		);

	$this->get(route('register'))->assertNotFound();

	$this->post(route('register.store'), [
		'name' => 'Test User',
		'email' => 'test@example.com',
		'password' => 'password',
		'password_confirmation' => 'password',
	])->assertNotFound();

	$this->assertGuest();
});
