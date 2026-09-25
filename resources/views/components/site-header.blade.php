<header class="sticky top-0 z-40 border-b border-stone-200 bg-white/80 backdrop-blur dark:border-stone-800 dark:bg-stone-950/80">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            <span class="flex size-9 items-center justify-center rounded-lg bg-red-600 text-lg font-bold text-white">汉</span>
            <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
        </a>

        <nav class="hidden flex-1 items-center gap-6 text-sm font-medium text-stone-600 sm:flex dark:text-stone-300">
            <a href="{{ route('home') }}" @class(['hover:text-red-600', 'text-red-600' => request()->routeIs('home')])>Trang chủ</a>
            <a href="{{ route('home') }}#courses" class="hover:text-red-600">Khoá học</a>
            <a href="{{ route('words.index') }}" @class(['hover:text-red-600', 'text-red-600' => request()->routeIs('words.*')])>Từ vựng</a>
        </nav>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('words.index') }}"
                class="flex size-9 items-center justify-center rounded-lg text-stone-600 transition hover:bg-stone-100 sm:hidden dark:text-stone-300 dark:hover:bg-stone-800"
                aria-label="Tra từ vựng"
            >
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            </a>

            <button
                type="button"
                data-theme-toggle
                class="flex size-9 items-center justify-center rounded-lg text-stone-600 transition hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800"
                aria-label="Đổi giao diện sáng / tối"
            >
                {{-- Sun: light --}}
                <svg class="hidden size-5 theme-light:block" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                {{-- Moon: dark --}}
                <svg class="hidden size-5 theme-dark:block" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" /></svg>
                {{-- Monitor: follow the system --}}
                <svg class="hidden size-5 theme-system:block" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" /></svg>
            </button>

            @auth
                <a
                    href="{{ route('profile.edit') }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition hover:bg-stone-100 hover:text-red-600 dark:hover:bg-stone-800',
                        'text-red-600' => request()->routeIs('profile.*'),
                        'text-stone-700 dark:text-stone-200' => ! request()->routeIs('profile.*'),
                    ])
                    title="Hồ sơ cá nhân"
                >
                    <x-user-avatar :user="auth()->user()" class="size-7 text-xs" />
                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex size-9 items-center justify-center rounded-lg border border-stone-300 text-sm font-semibold text-stone-700 transition hover:bg-stone-100 sm:size-auto sm:px-4 sm:py-2 dark:border-stone-700 dark:text-stone-200 dark:hover:bg-stone-800"
                        aria-label="Đăng xuất"
                        title="Đăng xuất"
                    >
                        <svg class="size-5 sm:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" /></svg>
                        <span class="hidden sm:inline">Đăng xuất</span>
                    </button>
                </form>
            @else
                <a href="{{ route('register') }}" class="hidden text-sm font-semibold text-stone-700 hover:text-red-600 sm:inline dark:text-stone-200">
                    Đăng ký
                </a>
                <a
                    href="{{ route('login') }}"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                >
                    Đăng nhập
                </a>
            @endauth
        </div>
    </div>
</header>
