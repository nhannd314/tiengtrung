<x-layouts.app title="Quên mật khẩu">
    <x-auth-card title="Quên mật khẩu" subtitle="Nhập email hoặc số điện thoại đã đăng ký, chúng tôi sẽ gửi link đặt lại mật khẩu đến email của bạn.">
        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <x-form.input name="login" label="Email hoặc số điện thoại" autocomplete="username" autofocus required />

            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                Gửi link đặt lại mật khẩu
            </button>
        </form>

        <x-slot:footer>
            Đã nhớ mật khẩu?
            <a href="{{ route('login') }}" class="font-semibold text-red-600 hover:underline">Đăng nhập</a>
        </x-slot:footer>
    </x-auth-card>
</x-layouts.app>
