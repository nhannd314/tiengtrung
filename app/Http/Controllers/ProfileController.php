<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * The old avatar file is deleted by the User model once the column changes.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'phone', 'address']));

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', User::AVATAR_DISK);
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar = null;
        }

        $user->save();

        return redirect()->route('profile.edit')->with('profile_status', 'Đã cập nhật thông tin cá nhân.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return redirect()->route('profile.edit')->with('password_status', 'Đã đổi mật khẩu.');
    }
}
