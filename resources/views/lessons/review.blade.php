@php
    use App\Support\LessonReview;

    $partInfo = LessonReview::PARTS[$part];
    $config = [
        'questions' => $questions,
        'saveUrl' => route('lessons.review.store', [$course, $lesson, $part]),
        'canSave' => auth()->check(),
        'input' => $partInfo['input'],
        'pronunciationUrl' => route('lessons.review.pronunciation', [$course, $lesson]),
        'passScore' => LessonReview::PRONUNCIATION_PASS_SCORE,
    ];
    $buttonClass = 'rounded-lg px-5 py-2.5 text-sm font-semibold';
@endphp

<x-layouts.app :title="'Ôn tập phần '.$part.': '.$lesson->title">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <div class="flex items-end justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <a href="{{ route('lessons.show', [$course, $lesson]) }}" class="text-sm text-stone-500 hover:text-red-600 dark:text-stone-400">← {{ $lesson->title }}</a>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Ôn tập bài học</h1>
                </div>

                <div class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3.5 py-2 font-mono text-lg font-semibold tabular-nums dark:border-stone-800 dark:bg-stone-900" aria-label="Thời gian làm phần này">
                    <svg class="size-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <span data-quiz="timer">0:00</span>
                </div>
            </div>

            {{-- Parts --}}
            <nav class="grid grid-cols-3 gap-2 sm:grid-cols-5" aria-label="Các phần ôn tập">
                @foreach (range(1, LessonReview::TOTAL_PARTS) as $number)
                    @if (LessonReview::exists($number))
                        <a href="{{ route('lessons.review', [$course, $lesson, $number]) }}" @class([
                            'flex flex-col items-center rounded-xl border-2 px-2 py-2 text-center transition',
                            'border-red-600 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => $number === $part,
                            'border-green-500 text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-950' => $number !== $part && $results->has($number),
                            'border-stone-200 text-stone-600 hover:border-red-300 dark:border-stone-800 dark:text-stone-300' => $number !== $part && ! $results->has($number),
                        ]) @if ($number === $part) aria-current="page" @endif>
                            <span class="text-xs font-semibold">Phần {{ $number }}{{ $results->has($number) ? ' ✓' : '' }}</span>
                            <span class="line-clamp-1 text-[11px] opacity-80">{{ LessonReview::PARTS[$number]['title'] }}</span>
                        </a>
                    @else
                        <span class="flex flex-col items-center rounded-xl border-2 border-dashed border-stone-200 px-2 py-2 text-center text-stone-400 dark:border-stone-800" title="Sắp ra mắt">
                            <span class="text-xs font-semibold">Phần {{ $number }}</span>
                            <span class="text-[11px]">Sắp ra mắt</span>
                        </span>
                    @endif
                @endforeach
            </nav>

            <section class="flex flex-col gap-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold">Phần {{ $part }}: {{ $partInfo['title'] }}</h2>
                        <p class="text-sm text-stone-500 dark:text-stone-400">{{ $partInfo['instruction'] }}</p>
                    </div>
                    <span class="text-sm font-medium text-stone-600 dark:text-stone-300" data-quiz="counter"></span>
                </div>

                @if (empty($questions))
                    <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center text-stone-500 dark:border-stone-700 dark:text-stone-400">
                        Bài học này chưa có từ vựng để ôn tập.
                    </div>
                @else
                    <div id="review-quiz" class="flex flex-col gap-5">
                        <div class="h-2 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800">
                            <div class="h-full rounded-full bg-red-600 transition-all" style="width: 0%" data-quiz="progress"></div>
                        </div>

                        {{-- Prompt --}}
                        <div class="flex min-h-56 flex-col items-center justify-center gap-3 rounded-3xl border border-stone-200 bg-white px-6 py-10 text-center shadow-sm dark:border-stone-800 dark:bg-stone-900">
                            @if ($partInfo['prompt'] === 'hanzi')
                                <span data-quiz="hanzi" class="text-7xl font-medium sm:text-8xl"></span>
                            @elseif ($partInfo['prompt'] === 'hanzi_pinyin_audio')
                                <span data-quiz="hanzi" class="text-6xl font-medium sm:text-7xl"></span>
                                <span data-quiz="pinyin" class="text-xl font-semibold text-red-600 dark:text-red-400"></span>
                                <button type="button" data-quiz="speak" data-speak="" class="flex size-11 items-center justify-center rounded-full bg-red-50 text-red-600 transition hover:bg-red-100 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900" aria-label="Nghe phát âm">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                                </button>
                            @else
                                <span class="text-xs font-semibold tracking-wider text-stone-400 uppercase">Nghĩa tiếng Việt</span>
                                <span data-quiz="meaning" class="text-3xl font-semibold sm:text-4xl"></span>
                            @endif

                            @if ($partInfo['input'] === 'speech')
                                {{-- Spoken answer, scored by Azure --}}
                                <div class="mt-4 flex flex-col items-center gap-3">
                                    @auth
                                        <button
                                            type="button"
                                            data-quiz="record"
                                            class="group flex size-20 items-center justify-center rounded-full bg-red-600 text-white shadow-lg transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 data-[state=recording]:animate-pulse data-[state=recording]:bg-red-700 data-[state=recording]:ring-8 data-[state=recording]:ring-red-200 dark:data-[state=recording]:ring-red-900"
                                            aria-label="Thu âm"
                                        >
                                            {{-- Microphone / stop --}}
                                            <svg class="size-9 group-data-[state=recording]:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" /></svg>
                                            <span class="hidden size-7 rounded-md bg-white group-data-[state=recording]:block"></span>
                                        </button>
                                        <p data-quiz="record-status" class="text-sm text-stone-500 dark:text-stone-400">Bấm micro và nói từ tiếng Trung (tối đa 5 giây).</p>
                                    @else
                                        <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            <a href="{{ route('login') }}" class="font-semibold underline">Đăng nhập</a> để thu âm và chấm điểm phát âm.
                                        </p>
                                    @endauth
                                </div>
                            @endif
                        </div>

                        @if ($partInfo['input'] === 'speech')
                            <div data-quiz="speech-result" hidden class="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                                <div class="flex items-center gap-4">
                                    <span data-quiz="speech-score" class="text-4xl font-bold tabular-nums"></span>
                                    <div class="flex flex-1 flex-col">
                                        <span class="text-xs text-stone-500 dark:text-stone-400">Điểm phát âm (đạt từ {{ LessonReview::PRONUNCIATION_PASS_SCORE }})</span>
                                        <span class="text-sm">Bạn nói: <strong data-quiz="speech-recognized"></strong></span>
                                    </div>
                                    <button type="button" data-quiz="speak" data-speak="" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 transition hover:bg-red-100 dark:bg-red-950 dark:text-red-300" aria-label="Nghe phát âm mẫu" title="Nghe phát âm mẫu">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                                    </button>
                                </div>
                                <dl class="grid grid-cols-3 gap-3 text-center text-sm">
                                    @foreach (['accuracy' => 'Độ chính xác', 'fluency' => 'Độ trôi chảy', 'completeness' => 'Độ đầy đủ'] as $key => $label)
                                        <div class="rounded-lg bg-stone-50 px-2 py-2 dark:bg-stone-800">
                                            <dt class="text-xs text-stone-500 dark:text-stone-400">{{ $label }}</dt>
                                            <dd data-quiz="speech-{{ $key }}" class="font-semibold tabular-nums"></dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @elseif ($partInfo['input'] === 'typed')
                            {{-- Typed answer --}}
                            {{-- No submit button: pressing Enter submits the form. --}}
                            <form data-quiz="typed-form" class="flex flex-col gap-1.5" autocomplete="off">
                                <input
                                    type="text"
                                    data-quiz="typed-input"
                                    lang="zh"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    enterkeyhint="done"
                                    placeholder="Gõ chữ Hán hoặc pinyin, ví dụ: 你好 / nǐ hǎo / ni3hao3"
                                    aria-label="Câu trả lời, nhấn Enter để kiểm tra"
                                    class="w-full rounded-xl border-2 border-stone-200 bg-white px-4 py-3 text-xl outline-none transition placeholder:text-base placeholder:text-stone-400 focus:border-red-400 disabled:opacity-100 dark:border-stone-700 dark:bg-stone-900
                                        data-[state=correct]:border-green-500! data-[state=correct]:bg-green-50! data-[state=correct]:text-green-800 dark:data-[state=correct]:bg-green-950! dark:data-[state=correct]:text-green-300
                                        data-[state=wrong]:border-red-500! data-[state=wrong]:bg-red-50! data-[state=wrong]:text-red-800 dark:data-[state=wrong]:bg-red-950! dark:data-[state=wrong]:text-red-300"
                                >
                                <p class="text-xs text-stone-500 dark:text-stone-400">Nhấn <kbd class="rounded border border-stone-300 px-1 font-sans dark:border-stone-700">Enter</kbd> để kiểm tra</p>
                            </form>
                        @else
                        {{-- Options --}}
                        <div class="grid gap-3 sm:grid-cols-2">
                            @for ($i = 0; $i < LessonReview::OPTIONS_PER_QUESTION; $i++)
                                <button type="button" data-option="{{ $i }}" class="flex items-center gap-3 rounded-xl border-2 border-stone-200 bg-white px-4 py-3.5 text-left transition hover:border-red-300 disabled:cursor-default disabled:hover:border-stone-200 dark:border-stone-700 dark:bg-stone-900 dark:disabled:hover:border-stone-700
                                    data-[state=correct]:border-green-500! data-[state=correct]:bg-green-50! data-[state=correct]:text-green-800 dark:data-[state=correct]:bg-green-950! dark:data-[state=correct]:text-green-300
                                    data-[state=wrong]:border-red-500! data-[state=wrong]:bg-red-50! data-[state=wrong]:text-red-800 dark:data-[state=wrong]:bg-red-950! dark:data-[state=wrong]:text-red-300">
                                    <kbd class="flex size-6 shrink-0 items-center justify-center rounded-md bg-stone-100 text-xs font-semibold text-stone-500 dark:bg-stone-800">{{ $i + 1 }}</kbd>
                                    <span class="flex flex-col">
                                        <span data-option-text @class(['font-medium', 'text-2xl' => $partInfo['answer_field'] === 'hanzi'])></span>
                                        <span data-option-hint hidden class="text-sm text-red-600 dark:text-red-400"></span>
                                    </span>
                                </button>
                            @endfor
                        </div>
                        @endif

                        <div data-quiz="feedback" class="hidden items-center justify-between gap-4 rounded-xl px-4 py-3">
                            <span data-quiz="feedback-text" class="text-sm font-semibold"></span>
                            <button type="button" data-quiz="next" class="rounded-lg bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">
                                Tiếp tục <kbd class="ml-1 text-xs opacity-60">Enter</kbd>
                            </button>
                        </div>
                    </div>

                    {{-- Result: shown and saved automatically after the last question --}}
                    <div data-quiz="result" hidden class="flex flex-col items-center gap-4 rounded-3xl border border-stone-200 bg-white p-8 text-center dark:border-stone-800 dark:bg-stone-900">
                        <span data-quiz="result-emoji" class="text-5xl"></span>
                        <h3 class="text-xl font-bold">Kết quả phần {{ $part }}</h3>

                        <div class="flex items-center gap-8">
                            <div class="flex flex-col">
                                <span class="text-xs text-stone-500 dark:text-stone-400">Số câu đúng</span>
                                <span data-quiz="score" class="text-3xl font-bold text-red-600 dark:text-red-400"></span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs text-stone-500 dark:text-stone-400">Thời gian</span>
                                <span data-quiz="time" class="font-mono text-3xl font-bold tabular-nums"></span>
                            </div>
                        </div>

                        <div data-quiz="mistakes" hidden class="flex w-full flex-col gap-2 text-left">
                            <p class="text-sm font-semibold text-stone-600 dark:text-stone-300">Các từ cần xem lại:</p>
                            <ul data-quiz="mistake-list" class="flex flex-col divide-y divide-stone-200 rounded-xl border border-stone-200 dark:divide-stone-800 dark:border-stone-800"></ul>
                        </div>

                        @auth
                            <p data-quiz="save-status" class="text-sm"></p>
                        @else
                            <p class="text-sm text-stone-500 dark:text-stone-400">
                                <a href="{{ route('login') }}" class="font-semibold text-red-600 hover:underline">Đăng nhập</a> để lưu kết quả ôn tập.
                            </p>
                        @endauth

                        <div class="flex flex-wrap justify-center gap-3">
                            <a href="{{ route('lessons.review', [$course, $lesson, $part]) }}" class="{{ $buttonClass }} border border-stone-300 hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800">Làm lại</a>

                            @if ($nextPart && LessonReview::exists($nextPart))
                                <a href="{{ route('lessons.review', [$course, $lesson, $nextPart]) }}" class="{{ $buttonClass }} bg-red-600 text-white hover:bg-red-700">Phần tiếp theo →</a>
                            @elseif ($nextPart)
                                <span class="{{ $buttonClass }} cursor-not-allowed bg-stone-200 text-stone-500 dark:bg-stone-800" title="Phần {{ $nextPart }} sắp ra mắt">Phần tiếp theo (sắp ra mắt)</span>
                            @else
                                <a href="{{ route('lessons.show', [$course, $lesson]) }}" class="{{ $buttonClass }} bg-red-600 text-white hover:bg-red-700">Hoàn thành</a>
                            @endif
                        </div>
                    </div>

                    <script type="application/json" id="review-quiz-config">@json($config)</script>
                @endif
            </section>
        </div>

        <aside class="lg:pt-8">
            <div data-quiz="stats" class="lg:sticky lg:top-24">
                @include('lessons.partials.review-stats')
            </div>
        </aside>
    </div>
</x-layouts.app>
