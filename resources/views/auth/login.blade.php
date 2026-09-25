<x-layouts.app title="Đăng nhập">
    <x-auth-card title="Đăng nhập" subtitle="Chào mừng bạn quay lại! 欢迎回来">
        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
            @csrf

            <x-form.input name="login" label="Email hoặc số điện thoại" autocomplete="username" autofocus required />
            <x-form.input name="password" type="password" label="Mật khẩu" autocomplete="current-password" required />

            <div class="flex items-center justify-between gap-4 text-sm">
                <label class="flex items-center gap-2 text-stone-600 dark:text-stone-400">
                    <input type="checkbox" name="remember" class="size-4 rounded border-stone-300 accent-red-600">
                    Ghi nhớ đăng nhập
                </label>

                <a href="{{ route('password.request') }}" class="font-medium text-red-600 hover:underline">Quên mật khẩu?</a>
            </div>

            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                Đăng nhập
            </button>
        </form>

        <x-slot:footer>
            Chưa có tài khoản?
            <a href="{{ route('register') }}" class="font-semibold text-red-600 hover:underline">Đăng ký ngay</a>
        </x-slot:footer>
    </x-auth-card>
</x-layouts.app>
