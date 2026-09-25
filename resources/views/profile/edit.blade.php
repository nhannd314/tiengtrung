<x-layouts.app title="Hồ sơ cá nhân">
    <div class="mx-auto flex max-w-2xl flex-col gap-8 px-4 py-12 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight">Hồ sơ cá nhân</h1>

        <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="text-lg font-semibold">Thông tin cá nhân</h2>
            <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">Cập nhật tên, ảnh đại diện, số điện thoại và địa chỉ của bạn.</p>

            @if (session('profile_status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                    {{ session('profile_status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-5">
                @csrf
                @method('PATCH')

                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-medium text-stone-700 dark:text-stone-300">Ảnh đại diện</span>
                    <div class="flex items-center gap-4">
                        <x-user-avatar :user="$user" class="size-16 text-2xl" />

                        <div class="flex flex-col gap-2">
                            <input
                                id="avatar"
                                name="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="text-sm text-stone-600 file:mr-3 file:rounded-lg file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-stone-700 hover:file:bg-stone-200 dark:text-stone-300 dark:file:bg-stone-800 dark:file:text-stone-200 dark:hover:file:bg-stone-700"
                            >
                            <p class="text-xs text-stone-500 dark:text-stone-400">JPG, PNG hoặc WEBP, tối đa 2 MB.</p>

                            @if ($user->avatar)
                                <label class="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-300">
                                    <input type="checkbox" name="remove_avatar" value="1" class="rounded border-stone-300 text-red-600 focus:ring-red-500 dark:border-stone-700">
                                    Xoá ảnh đại diện
                                </label>
                            @endif
                        </div>
                    </div>

                    @error('avatar')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-form.input name="name" label="Họ và tên" :value="$user->name" autocomplete="name" required />

                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-sm font-medium text-stone-700 dark:text-stone-300">Email</label>
                    <input
                        id="email"
                        type="email"
                        value="{{ $user->email }}"
                        disabled
                        class="cursor-not-allowed rounded-lg border border-stone-200 bg-stone-100 px-3.5 py-2.5 text-sm text-stone-500 dark:border-stone-800 dark:bg-stone-800/50 dark:text-stone-400"
                    >
                    <p class="text-xs text-stone-500 dark:text-stone-400">Email không thể thay đổi.</p>
                </div>

                <x-form.input name="phone" type="tel" label="Số điện thoại" :value="$user->phone" placeholder="0912345678" autocomplete="tel" inputmode="tel" required />
                <x-form.input name="address" label="Địa chỉ" :value="$user->address" autocomplete="street-address" />

                <div>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                        Lưu thay đổi
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="text-lg font-semibold">Đổi mật khẩu</h2>
            <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">Nhập mật khẩu hiện tại để đặt mật khẩu mới.</p>

            @if (session('password_status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                    {{ session('password_status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-6 flex flex-col gap-5">
                @csrf
                @method('PUT')

                <x-form.input name="current_password" type="password" label="Mật khẩu hiện tại" autocomplete="current-password" required />
                <x-form.input name="password" type="password" label="Mật khẩu mới" autocomplete="new-password" required />
                <x-form.input name="password_confirmation" type="password" label="Nhập lại mật khẩu mới" autocomplete="new-password" required />

                <div>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                        Đổi mật khẩu
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
