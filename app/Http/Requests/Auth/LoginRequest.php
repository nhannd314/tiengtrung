<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => 'email hoặc số điện thoại',
            'password' => 'mật khẩu',
        ];
    }

    /**
     * Authenticate by email or phone number. Inactive accounts are rejected.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->credentials())) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'Thông tin đăng nhập không chính xác.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản của bạn chưa được kích hoạt. Vui lòng chờ quản trị viên duyệt.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
    }

    /**
     * @return array<string, string>
     */
    protected function credentials(): array
    {
        return LoginIdentifier::credentials($this->string('login'))
            + ['password' => $this->string('password')->value()];
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
