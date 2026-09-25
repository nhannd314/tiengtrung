<x-layouts.app title="Từ vựng">
    <div class="mx-auto flex max-w-4xl flex-col gap-8 px-4 py-10 sm:px-6">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Tra từ vựng</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Tìm theo chữ Hán, pinyin (có hoặc không dấu), Hán Việt hoặc nghĩa tiếng Việt.</p>
        </div>

        <form method="GET" action="{{ route('words.index') }}" role="search" class="flex gap-2">
            <label for="q" class="sr-only">Từ khoá</label>
            <input
                id="q"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="Ví dụ: 你好, nihao, hảo, xin chào"
                maxlength="50"
                autocomplete="off"
                @if ($search === '') autofocus @endif
                class="min-w-0 flex-1 rounded-lg border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-xs outline-none transition placeholder:text-stone-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-stone-700 dark:bg-stone-900"
            >
            <button type="submit" class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                Tìm
            </button>
        </form>

        @if ($words !== null)
            <div class="flex flex-col gap-4">
                <p class="text-sm text-stone-500 dark:text-stone-400">
                    @if ($words->total() > 0)
                        Tìm thấy <strong class="text-stone-900 dark:text-stone-100">{{ $words->total() }}</strong> từ cho “{{ $search }}”
                    @else
                        Không tìm thấy từ nào cho “{{ $search }}”. Hãy thử từ khoá khác.
                    @endif
                </p>

                <ol class="flex flex-col gap-3">
                    @foreach ($words as $word)
                        <li class="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-4 sm:flex-row sm:p-5 dark:border-stone-800 dark:bg-stone-900">
                            <div class="flex shrink-0 items-center gap-3 sm:w-40 sm:flex-col sm:items-center sm:justify-center sm:gap-1 sm:border-r sm:border-stone-200 sm:pr-5 sm:text-center dark:sm:border-stone-800">
                                <span class="text-4xl font-medium">{{ $word->hanzi }}</span>
                                <div class="flex flex-col sm:items-center">
                                    <span class="font-semibold text-red-600 dark:text-red-400">{{ $word->pinyin }}</span>
                                    @if ($word->traditional && $word->traditional !== $word->hanzi)
                                        <span class="text-xs text-stone-500">Phồn thể: {{ $word->traditional }}</span>
                                    @endif
                                </div>
                                <button
                                    type="button"
                                    data-speak="{{ $word->hanzi }}"
                                    @if ($word->audio_src) data-audio="{{ $word->audio_src }}" @endif
                                    class="ml-auto flex size-9 items-center justify-center rounded-full bg-red-50 text-red-600 transition hover:bg-red-100 sm:ml-0 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900"
                                    aria-label="Nghe phát âm {{ $word->hanzi }}"
                                >
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                                </button>
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <div class="flex items-start gap-2">
                                    <ol class="flex min-w-0 flex-1 flex-col gap-1">
                                        @foreach ($word->meanings as [$partOfSpeech, $meaning])
                                            <li class="flex flex-wrap items-center gap-2">
                                                @if (count($word->meanings) > 1)
                                                    <span class="text-sm text-stone-400 tabular-nums dark:text-stone-500">{{ $loop->iteration }}.</span>
                                                @endif
                                                @if ($partOfSpeech)
                                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600 dark:bg-stone-800 dark:text-stone-300">{{ $partsOfSpeech[$partOfSpeech] ?? $partOfSpeech }}</span>
                                                @endif
                                                <span class="text-lg font-semibold">{{ $meaning }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                    @if ($word->hsk_level)
                                        <span class="mt-1 shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300">HSK {{ $word->hsk_level }}</span>
                                    @endif
                                </div>

                                @if ($word->han_viet)
                                    <p class="text-sm text-stone-600 dark:text-stone-400">Hán Việt: <span class="font-medium uppercase">{{ $word->han_viet }}</span></p>
                                @endif

                                <div class="mt-1 border-t border-stone-100 pt-3 dark:border-stone-800">
                                    @if ($word->lessons->isEmpty())
                                        <p class="text-sm text-stone-400 dark:text-stone-500">Chưa có bài học nào chứa từ này.</p>
                                    @else
                                        <p class="mb-2 text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">Có trong {{ $word->lessons->count() }} bài học</p>
                                        <ul class="flex flex-wrap gap-2">
                                            @foreach ($word->lessons as $lesson)
                                                <li>
                                                    <a
                                                        href="{{ route('lessons.show', [$lesson->course, $lesson]) }}"
                                                        class="flex flex-col rounded-lg border border-stone-200 px-3 py-1.5 text-sm transition hover:border-red-300 hover:bg-red-50 dark:border-stone-700 dark:hover:border-red-800 dark:hover:bg-red-950"
                                                    >
                                                        <span class="font-medium">{{ $lesson->title }}</span>
                                                        <span class="text-xs text-stone-500 dark:text-stone-400">{{ $lesson->course->title }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                {{ $words->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
