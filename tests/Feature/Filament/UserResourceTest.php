<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function newUserData(array $overrides = []): array
{
    return [
        'name' => 'Nguyễn Văn A',
        'email' => 'A@Example.com',
        'phone' => '+84 912 345 678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => User::ROLE_USER,
        'is_active' => true,
        ...$overrides,
    ];
}

test('admins can create users, including role and active status', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['role' => User::ROLE_ADMIN, 'is_active' => false]))
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::firstWhere('email', 'a@example.com');
    expect($user)->not->toBeNull()
        ->and($user->phone)->toBe('0912345678')
        ->and($user->role)->toBe(User::ROLE_ADMIN)
        ->and($user->is_active)->toBeFalse()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

test('admins can activate a new sign-up', function () {
    $user = User::factory()->inactive()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['is_active' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->is_active)->toBeTrue();
});

test('password is required on create but can be left blank on edit', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['password' => null, 'password_confirmation' => null]))
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);

    $user = User::factory()->create();
    $hash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Tên mới'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->password)->toBe($hash)
        ->and($user->fresh()->name)->toBe('Tên mới');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('password must be confirmed', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['password_confirmation' => 'different']))
        ->call('create')
        ->assertHasFormErrors(['password' => 'confirmed']);
});

test('email and phone must be unique and the phone valid', function () {
    User::factory()->create(['email' => 'taken@example.com', 'phone' => '0912345678']);

    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['email' => 'taken@example.com', 'phone' => '0912 345 678']))
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique', 'phone']);

    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['phone' => '12345']))
        ->call('create')
        ->assertHasFormErrors(['phone']);
});

test('a user keeps their own phone number when edited', function () {
    $user = User::factory()->create(['phone' => '0912345678']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'X'])
        ->call('save')
        ->assertHasNoFormErrors();
});

test('admins cannot change their own role, deactivate or delete themselves', function () {
    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->assertFormFieldDisabled('role')
        ->assertFormFieldDisabled('is_active')
        ->assertActionHidden('delete')
        ->fillForm(['name' => 'Admin mới'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->admin->fresh())
        ->role->toBe(User::ROLE_ADMIN)
        ->is_active->toBeTrue()
        ->name->toBe('Admin mới');

    Livewire::test(EditUser::class, ['record' => User::factory()->create()->getRouteKey()])
        ->assertFormFieldEnabled('role')
        ->assertActionVisible('delete');
});

test('avatar is uploaded to the public disk and replaced files are deleted', function () {
    Storage::fake(User::AVATAR_DISK);
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['avatar' => UploadedFile::fake()->image('a.jpg')])
        ->call('save')
        ->assertHasNoFormErrors();

    $first = $user->fresh()->avatar;
    Storage::disk(User::AVATAR_DISK)->assertExists($first);
    expect($first)->toStartWith('avatars/');

    // Replacing the avatar (the form stores the new path the same way) deletes the old file.
    Storage::disk(User::AVATAR_DISK)->put('avatars/new.jpg', 'img');
    $user->fresh()->update(['avatar' => 'avatars/new.jpg']);
    Storage::disk(User::AVATAR_DISK)->assertMissing($first);

    // Removing it, or deleting the user, deletes the file too.
    $user->fresh()->delete();
    Storage::disk(User::AVATAR_DISK)->assertMissing('avatars/new.jpg');
});

test('inactive admins cannot open the admin panel', function () {
    $this->actingAs(User::factory()->admin()->inactive()->create())
        ->get('/admin')
        ->assertForbidden();

    $this->actingAs($this->admin)->get('/admin')->assertOk();
});

test('users list can be filtered to accounts waiting for activation', function () {
    $pending = User::factory()->inactive()->create();
    $active = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active, $this->admin]);
});
