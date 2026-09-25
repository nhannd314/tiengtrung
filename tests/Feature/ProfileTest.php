<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'hoc@example.com',
        'phone' => '0912345678',
    ]);
});

function validProfile(array $overrides = []): array
{
    return [
        'name' => 'Trần Thị B',
        'phone' => '0987654321',
        'address' => '1 Tràng Tiền, Hà Nội',
        ...$overrides,
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->patch(route('profile.update'), validProfile())->assertRedirect(route('login'));
    $this->put(route('profile.password.update'))->assertRedirect(route('login'));
});

test('header links the user name to the profile page', function () {
    $this->actingAs($this->user)
        ->get(route('home'))
        ->assertSee(route('profile.edit'));
});

test('profile page renders the current details', function () {
    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('hoc@example.com')
        ->assertSee('0912345678');
});

test('user can update name, phone and address', function () {
    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['phone' => '+84 987.654.321']))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('profile_status');

    expect($this->user->fresh())
        ->name->toBe('Trần Thị B')
        ->phone->toBe('0987654321')
        ->address->toBe('1 Tràng Tiền, Hà Nội');
});

test('email cannot be changed', function () {
    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['email' => 'other@example.com']));

    expect($this->user->fresh()->email)->toBe('hoc@example.com');
});

test('user can keep their own phone number', function () {
    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['phone' => '0912345678']))
        ->assertSessionHasNoErrors();
});

test('phone must be valid and not used by another account', function (string $phone) {
    User::factory()->create(['phone' => '0911111111']);

    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['phone' => $phone]))
        ->assertSessionHasErrors('phone');

    expect($this->user->fresh()->phone)->toBe('0912345678');
})->with(['12345', '0911 111 111']);

test('uploading an avatar replaces the old file', function () {
    Storage::fake(User::AVATAR_DISK);
    Storage::disk(User::AVATAR_DISK)->put('avatars/old.png', 'old');
    $this->user->update(['avatar' => 'avatars/old.png']);

    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['avatar' => UploadedFile::fake()->image('me.png')]))
        ->assertSessionHasNoErrors();

    $avatar = $this->user->fresh()->avatar;
    expect($avatar)->toStartWith('avatars/');
    Storage::disk(User::AVATAR_DISK)->assertExists($avatar);
    Storage::disk(User::AVATAR_DISK)->assertMissing('avatars/old.png');
});

test('user can remove their avatar', function () {
    Storage::fake(User::AVATAR_DISK);
    Storage::disk(User::AVATAR_DISK)->put('avatars/old.png', 'old');
    $this->user->update(['avatar' => 'avatars/old.png']);

    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['remove_avatar' => '1']));

    expect($this->user->fresh()->avatar)->toBeNull();
    Storage::disk(User::AVATAR_DISK)->assertMissing('avatars/old.png');
});

test('avatar must be an image', function () {
    Storage::fake(User::AVATAR_DISK);

    $this->actingAs($this->user)
        ->patch(route('profile.update'), validProfile(['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors('avatar');
});

test('user can change password with the current password', function () {
    $this->actingAs($this->user)
        ->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('password_status');

    expect(Hash::check('new-password123', $this->user->fresh()->password))->toBeTrue();
});

test('password is not changed when the current password is wrong', function () {
    $this->actingAs($this->user)
        ->put(route('profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

test('new password must be confirmed', function () {
    $this->actingAs($this->user)
        ->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password123',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password');
});
