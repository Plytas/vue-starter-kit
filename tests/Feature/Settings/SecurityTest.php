<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed', function (): void {
	$this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

	Features::twoFactorAuthentication([
		'confirm' => true,
		'confirmPassword' => true,
	]);

	$user = User::factory()->withoutTwoFactor()->create()->fresh();

	$this->actingAs($user)
		->withSession(['auth.password_confirmed_at' => time()])
		->get(route('security.edit'))
		->assertInertia(
			fn(Assert $page) => $page
			->component('settings/Security')
			->where('canManageTwoFactor', true)
			->where('twoFactorEnabled', false)
			->where('canManagePasskeys', true)
			->where('passkeys', []),
		);
});

test('security page requires password confirmation when enabled', function (): void {
	$this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

	$user = User::factory()->create()->fresh();

	Features::twoFactorAuthentication([
		'confirm' => true,
		'confirmPassword' => true,
	]);

	$response = $this->actingAs($user)->get(route('security.edit'));

	$response->assertRedirect(route('password.confirm'));
});

test('security page does not require password confirmation when disabled', function (): void {
	$this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

	Features::twoFactorAuthentication([
		'confirm' => true,
		'confirmPassword' => false,
	]);

	$user = User::factory()->create()->fresh();

	$this->actingAs($user)
		->get(route('security.edit'))
		->assertOk()
		->assertInertia(
			fn(Assert $page) => $page
			->component('settings/Security'),
		);
});

test('security page renders without two factor when feature is disabled', function (): void {
	config(['fortify.features' => []]);

	$user = User::factory()->create()->fresh();

	$this->actingAs($user)
		->get(route('security.edit'))
		->assertOk()
		->assertInertia(
			fn(Assert $page) => $page
			->component('settings/Security')
			->where('canManageTwoFactor', false)
			->missing('twoFactorEnabled')
			->missing('requiresConfirmation')
			->where('canManagePasskeys', false)
			->where('passkeys', []),
		);
});

test('password can be updated', function (): void {
	$user = User::factory()->create();

	$response = $this
		->actingAs($user)
		->from(route('security.edit'))
		->put(route('security.update'), [
			'current_password' => 'password',
			'password' => 'new-password',
			'password_confirmation' => 'new-password',
		]);

	$response
		->assertSessionHasNoErrors()
		->assertRedirect(route('security.edit'))
		->assertInertiaFlash('toast', [
			'type' => 'success',
			'message' => 'Password updated.',
		]);

	expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function (): void {
	$user = User::factory()->create();

	$response = $this
		->actingAs($user)
		->from(route('security.edit'))
		->put(route('security.update'), [
			'current_password' => 'wrong-password',
			'password' => 'new-password',
			'password_confirmation' => 'new-password',
		]);

	$response
		->assertSessionHasErrors('current_password')
		->assertRedirect(route('security.edit'));
});

test('two factor columns are hidden from array serialization', function (): void {
	$array = User::factory()->create()->toArray();

	expect($array)->not->toHaveKey('two_factor_secret');
	expect($array)->not->toHaveKey('two_factor_recovery_codes');
});
