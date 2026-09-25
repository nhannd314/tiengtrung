<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * New accounts are inactive until an admin activates them, so the user is not logged in.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));

        event(new Registered($user));

        return redirect()->route('login')
            ->with('status', 'Đăng ký thành công! Tài khoản của bạn đang chờ quản trị viên kích hoạt.');
    }
}
