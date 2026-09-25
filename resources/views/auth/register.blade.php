<x-layouts.app title="Đăng ký">
    <x-auth-card title="Tạo tài khoản" subtitle="Bắt đầu hành trình học tiếng Trung của bạn">
        <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
            @csrf

            <x-form.input name="name" label="Họ và tên" autocomplete="name" autofocus required />
            <x-form.input name="email" type="email" label="Email" autocomplete="email" required />
            <x-form.input name="phone" type="tel" label="Số điện thoại" placeholder="0912345678" autocomplete="tel" inputmode="tel" required />
            <x-form.input name="password" type="password" label="Mật khẩu" autocomplete="new-password" required />
            <x-form.input name="password_confirmation" type="password" label="Nhập lại mật khẩu" autocomplete="new-password" required />

            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                Đăng ký
            </button>
        </form>

        <x-slot:footer>
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="font-semibold text-red-600 hover:underline">Đăng nhập</a>
        </x-slot:footer>
    </x-auth-card>
</x-layouts.app>
