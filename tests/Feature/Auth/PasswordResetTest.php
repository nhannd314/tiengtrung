<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create([
        'email' => 'hoc@example.com',
        'phone' => '0912345678',
    ]);
});

test('forgot password page renders', function () {
    $this->get(route('password.request'))->assertOk();
});

test('reset link is sent when requesting by email or phone', function (string $login) {
    $this->post(route('password.email'), ['login' => $login])
        ->assertSessionHas('status');

    Notification::assertSentTo($this->user, ResetPassword::class);
})->with(['hoc@example.com', '0912 345 678', '+84912345678']);

test('unknown accounts get the same response and no email', function () {
    $this->post(route('password.email'), ['login' => 'nobody@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('reset password page renders with the token', function () {
    $this->post(route('password.email'), ['login' => 'hoc@example.com']);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => 'hoc@example.com']))
            ->assertOk()
            ->assertSee($notification->token);

        return true;
    });
});

test('password can be reset with a valid token', function () {
    $this->post(route('password.email'), ['login' => 'hoc@example.com']);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => 'hoc@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        return true;
    });

    expect(Hash::check('new-password', $this->user->fresh()->password))->toBeTrue();

    $this->post(route('login'), ['login' => '0912345678', 'password' => 'new-password']);
    $this->assertAuthenticatedAs($this->user);
});

test('password cannot be reset with an invalid token', function () {
    $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => 'hoc@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});
