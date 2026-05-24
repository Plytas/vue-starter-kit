<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('two factor columns are hidden from array serialization', function (): void {
    $array = User::factory()->create()->toArray();

    expect($array)->not->toHaveKey('two_factor_secret');
    expect($array)->not->toHaveKey('two_factor_recovery_codes');
});

test('two factor settings page can be rendered', function (): void {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withoutTwoFactor()->create()->fresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TwoFactor')
            ->where('twoFactorEnabled', false)
            ->where('requiresConfirmation', true)
        );
});

test('two factor settings page requires password confirmation when enabled', function (): void {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    $user = User::factory()->create()->fresh();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)->get(route('two-factor.show'));

    $response->assertRedirect(route('password.confirm'));
});

test('two factor settings page does not require password confirmation when disabled', function (): void {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $user = User::factory()->create()->fresh();

    $this->actingAs($user)
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TwoFactor')
        );
});

test('two factor settings page returns forbidden when two factor is disabled', function (): void {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create()->fresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertForbidden();
});
