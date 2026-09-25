@props(['title', 'subtitle' => null])

<div class="mx-auto flex max-w-md flex-col gap-6 px-4 py-12 sm:py-16">
    <div class="flex flex-col items-center gap-2 text-center">
        <span class="flex size-12 items-center justify-center rounded-xl bg-red-600 text-2xl font-bold text-white">汉</span>
        <h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-stone-500 dark:text-stone-400">{{ $subtitle }}</p>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8 dark:border-stone-800 dark:bg-stone-900">
        {{ $slot }}
    </div>

    @isset($footer)
        <p class="text-center text-sm text-stone-600 dark:text-stone-400">{{ $footer }}</p>
    @endisset
</div>
