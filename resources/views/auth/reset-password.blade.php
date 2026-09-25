<x-layouts.app title="Đặt lại mật khẩu">
    <x-auth-card title="Đặt lại mật khẩu" :subtitle="$email ? 'Tài khoản: '.$email : null">
        <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ old('email', $email) }}">

            @error('email')
                <p class="text-sm text-red-600 dark:text-red-400">Link đặt lại mật khẩu không hợp lệ. Vui lòng yêu cầu link mới.</p>
            @enderror

            <x-form.input name="password" type="password" label="Mật khẩu mới" autocomplete="new-password" autofocus required />
            <x-form.input name="password_confirmation" type="password" label="Nhập lại mật khẩu mới" autocomplete="new-password" required />

            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                Đặt lại mật khẩu
            </button>
        </form>

        <x-slot:footer>
            Link hết hạn?
            <a href="{{ route('password.request') }}" class="font-semibold text-red-600 hover:underline">Gửi lại link</a>
        </x-slot:footer>
    </x-auth-card>
</x-layouts.app>
