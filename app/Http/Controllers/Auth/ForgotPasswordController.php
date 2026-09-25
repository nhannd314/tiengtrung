<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a reset link to the account matching the email or phone number.
     * The response is the same whether or not an account exists, so it cannot be used to probe accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['login' => ['required', 'string', 'max:255']],
            attributes: ['login' => 'email hoặc số điện thoại'],
        );

        Password::sendResetLink(LoginIdentifier::credentials($request->string('login')));

        return back()->with('status', 'Nếu tài khoản tồn tại, chúng tôi đã gửi link đặt lại mật khẩu đến email đã đăng ký. Vui lòng kiểm tra hộp thư.');
    }
}
