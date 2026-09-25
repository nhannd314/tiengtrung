<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'hoc@example.com',
        'phone' => '0912345678',
    ]);
});

test('login page renders', function () {
    $this->get(route('login'))->assertOk();
});

test('users can log in with email', function () {
    $this->post(route('login'), ['login' => 'Hoc@Example.com', 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->user);
});

test('users can log in with phone number in any format', function (string $phone) {
    $this->post(route('login'), ['login' => $phone, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->user);
})->with(['0912345678', '0912 345 678', '+84912345678']);

test('wrong password is rejected', function () {
    $this->post(route('login'), ['login' => 'hoc@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('inactive users cannot log in', function () {
    $this->user->forceFill(['is_active' => false])->save();

    $this->post(route('login'), ['login' => 'hoc@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Tài khoản của bạn chưa được kích hoạt. Vui lòng chờ quản trị viên duyệt.']);

    $this->assertGuest();
});

test('login is rate limited after 5 failed attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['login' => 'hoc@example.com', 'password' => 'wrong']);
    }

    $this->post(route('login'), ['login' => 'hoc@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('users can log out', function () {
    $this->actingAs($this->user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('authenticated users are redirected away from auth pages', function () {
    $this->actingAs($this->user)->get(route('login'))->assertRedirect();
    $this->actingAs($this->user)->get(route('register'))->assertRedirect();
});
