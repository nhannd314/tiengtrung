@php
    $lessonCount = $course->lessons->count();
    $completedCount = $completedLessonIds->count();
    $progress = $lessonCount > 0 ? (int) round($completedCount / $lessonCount * 100) : 0;
    $resumeLesson = $course->lessons->first(fn ($lesson) => ! $completedLessonIds->contains($lesson->id)) ?? $course->lessons->first();
    $duration = intdiv($totalMinutes, 60) > 0
        ? intdiv($totalMinutes, 60).' giờ'.($totalMinutes % 60 ? ' '.($totalMinutes % 60).' phút' : '')
        : $totalMinutes.' phút';
@endphp

<x-layouts.app :title="$course->title">
    {{-- Hero --}}
    <section class="bg-linear-to-br from-red-700 via-red-600 to-amber-500 text-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-10 sm:px-6 lg:py-14">
            <nav class="flex items-center gap-2 text-sm text-white/80" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-white">Trang chủ</a>
                <span>/</span>
                <a href="{{ route('home') }}#courses" class="hover:text-white">Khoá học</a>
            </nav>

            <div class="flex flex-wrap items-center gap-2">
                @if ($course->hsk_level)
                    <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold ring-1 ring-white/30">HSK {{ $course->hsk_level }}</span>
                @endif
                @unless ($course->is_published)
                    <span class="rounded-full bg-stone-900/40 px-3 py-1 text-xs font-semibold">Bản nháp — chỉ admin thấy</span>
                @endunless
            </div>

            <h1 class="max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">{{ $course->title }}</h1>

            @if ($course->description)
                <p class="max-w-3xl text-white/90">{{ $course->description }}</p>
            @endif

            <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <div class="flex items-center gap-2">
                    <dt class="sr-only">Số bài học</dt>
                    <svg class="size-5 text-white/70" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                    <dd><strong>{{ $lessonCount }}</strong> bài học</dd>
                </div>
                <div class="flex items-center gap-2">
                    <dt class="sr-only">Số từ vựng</dt>
                    <span class="text-lg leading-none font-bold text-white/70" aria-hidden="true">字</span>
                    <dd><strong>{{ $wordCount }}</strong> từ vựng</dd>
                </div>
                @if ($totalMinutes > 0)
                    <div class="flex items-center gap-2">
                        <dt class="sr-only">Thời lượng</dt>
                        <svg class="size-5 text-white/70" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <dd>{{ $duration }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-3">
        {{-- Sidebar: shown first on mobile so the call to action is visible early --}}
        <aside class="flex flex-col gap-6 lg:order-last">
            <div class="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm lg:sticky lg:top-24 dark:border-stone-800 dark:bg-stone-900">
                @if ($course->thumbnail_url)
                    <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="aspect-video w-full rounded-xl object-cover">
                @endif

                @if (session('status'))
                    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($enrollment)
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold">Tiến độ của bạn</span>
                            <span class="text-stone-500 dark:text-stone-400">{{ $completedCount }}/{{ $lessonCount }} bài</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-red-600" style="width: {{ $progress }}%"></div>
                        </div>
                        <p class="text-xs text-stone-500 dark:text-stone-400">Đăng ký ngày {{ \Illuminate\Support\Carbon::parse($enrollment->enrolled_at)->format('d/m/Y') }}</p>
                    </div>

                    <a href="{{ $resumeLesson ? route('lessons.show', [$course, $resumeLesson]) : '#lessons' }}" class="rounded-lg bg-red-600 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                        {{ $completedCount > 0 ? 'Tiếp tục học' : 'Bắt đầu học' }}
                    </a>
                @else
                    <form method="POST" action="{{ route('courses.enroll', $course) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                            Đăng ký khoá học
                        </button>
                    </form>

                    @guest
                        <p class="text-center text-xs text-stone-500 dark:text-stone-400">
                            Bạn cần <a href="{{ route('login') }}" class="font-semibold text-red-600 hover:underline">đăng nhập</a> để đăng ký khoá học.
                        </p>
                    @endguest
                @endif

                <ul class="flex flex-col gap-2 border-t border-stone-200 pt-4 text-sm text-stone-600 dark:border-stone-800 dark:text-stone-400">
                    <li class="flex gap-2"><span class="text-red-600">✓</span> Từ vựng có pinyin, âm Hán Việt và ví dụ</li>
                    <li class="flex gap-2"><span class="text-red-600">✓</span> Tài liệu đính kèm cho từng bài</li>                </ul>
            </div>
        </aside>

        <div class="flex flex-col gap-10 lg:col-span-2">
            {{-- Lessons --}}
            <section id="lessons" class="scroll-mt-24">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <h2 class="text-xl font-bold tracking-tight">Nội dung khoá học</h2>
                    <span class="text-sm text-stone-500 dark:text-stone-400">{{ $lessonCount }} bài · {{ $duration }}</span>
                </div>

                @if ($course->lessons->isEmpty())
                    <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center text-stone-500 dark:border-stone-700 dark:text-stone-400">
                        Khoá học đang được cập nhật bài học.
                    </div>
                @else
                    <ol class="divide-y divide-stone-200 overflow-hidden rounded-2xl border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                        @foreach ($course->lessons as $lesson)
                            @php
                                $completed = $completedLessonIds->contains($lesson->id);
                                $locked = ! $enrollment && ! $lesson->is_free && ! auth()->user()?->isAdmin();
                            @endphp

                            <li>
                            <{{ $locked ? 'div' : 'a' }}
                                @unless ($locked) href="{{ route('lessons.show', [$course, $lesson]) }}" @endunless
                                @class(['flex items-start gap-4 p-4 sm:p-5', 'transition hover:bg-stone-50 dark:hover:bg-stone-800/50' => ! $locked])
                            >
                                <span @class([
                                    'flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                    'bg-green-600 text-white' => $completed,
                                    'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => ! $completed,
                                ])>
                                    @if ($completed)
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-label="Đã hoàn thành"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </span>

                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold">{{ $lesson->title }}</h3>
                                        @if ($lesson->is_free && ! $enrollment)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Học thử</span>
                                        @endif
                                    </div>

                                    @if ($lesson->summary)
                                        <p class="text-sm text-stone-600 dark:text-stone-400">{{ $lesson->summary }}</p>
                                    @endif

                                    <p class="flex flex-wrap gap-x-4 text-xs text-stone-500 dark:text-stone-400">
                                        <span>{{ $lesson->words_count }} từ vựng</span>
                                        @if ($lesson->duration_minutes)
                                            <span>{{ $lesson->duration_minutes }} phút</span>
                                        @endif
                                    </p>
                                </div>

                                @if ($locked)
                                    <svg class="mt-1.5 size-5 shrink-0 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-label="Cần đăng ký khoá học"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                @else
                                    <svg class="mt-1.5 size-5 shrink-0 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                @endif
                            </{{ $locked ? 'div' : 'a' }}>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Vocabulary preview --}}
            @if ($previewWords->isNotEmpty())
                <section>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <h2 class="text-xl font-bold tracking-tight">Từ vựng trong khoá</h2>
                        @if ($wordCount > $previewWords->count())
                            <span class="text-sm text-stone-500 dark:text-stone-400">và {{ $wordCount - $previewWords->count() }} từ khác</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        @foreach ($previewWords as $word)
                            <div class="flex flex-col items-center gap-1 rounded-xl border border-stone-200 bg-white p-4 text-center dark:border-stone-800 dark:bg-stone-900">
                                <span class="text-3xl font-medium">{{ $word->hanzi }}</span>
                                <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $word->pinyin }}</span>
                                <span class="line-clamp-1 text-xs text-stone-500 dark:text-stone-400">{{ $word->meaning_text }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
