<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validRegistration(array $overrides = []): array
{
    return [
        'name' => 'Nguyễn Văn A',
        'email' => 'a@example.com',
        'phone' => '0912345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        ...$overrides,
    ];
}

test('registration page renders', function () {
    $this->get(route('register'))->assertOk();
});

test('new users are created inactive and are not logged in', function () {
    $this->post(route('register'), validRegistration())
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();

    $user = User::firstWhere('email', 'a@example.com');
    expect($user->phone)->toBe('0912345678')
        ->and($user->role)->toBe(User::ROLE_USER)
        ->and($user->is_active)->toBeFalse();
});

test('phone number is normalized before saving', function () {
    $this->post(route('register'), validRegistration(['phone' => '+84 912.345.678']));

    expect(User::firstWhere('email', 'a@example.com')->phone)->toBe('0912345678');
});

test('email and phone are required', function () {
    $this->post(route('register'), validRegistration(['email' => '', 'phone' => '']))
        ->assertSessionHasErrors(['email', 'phone']);

    expect(User::count())->toBe(0);
});

test('invalid phone number is rejected', function () {
    $this->post(route('register'), validRegistration(['phone' => '12345']))
        ->assertSessionHasErrors('phone');
});

test('email and phone must be unique', function () {
    User::factory()->create(['email' => 'a@example.com', 'phone' => '0912345678']);

    $this->post(route('register'), validRegistration(['phone' => '+84912345678']))
        ->assertSessionHasErrors(['email', 'phone']);
});

test('role and is_active cannot be set through registration', function () {
    $this->post(route('register'), validRegistration(['role' => 'admin', 'is_active' => true]));

    $user = User::firstWhere('email', 'a@example.com');
    expect($user->role)->toBe(User::ROLE_USER)
        ->and($user->is_active)->toBeFalse();
});
