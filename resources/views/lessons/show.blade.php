@php
    $partsOfSpeech = \App\Models\Word::PARTS_OF_SPEECH;
    $isCompleted = $completedLessonIds->contains($lesson->id);
    $lessonNumber = $lessons->search(fn ($item) => $item->is($lesson));
    $attachments = $lesson->attachmentFiles();
@endphp

<x-layouts.app :title="$lesson->title">
    <div class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 sm:px-6">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-stone-500 dark:text-stone-400" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-red-600">Trang chủ</a>
                <span>/</span>
                <a href="{{ route('courses.show', $course) }}" class="hover:text-red-600">{{ $course->title }}</a>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    @if ($lessonNumber !== false)
                        <span class="text-red-600">Bài {{ $lessonNumber + 1 }}:</span>
                    @endif
                    {{ $lesson->title }}
                </h1>
                @if ($isCompleted)
                    <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800 dark:bg-green-950 dark:text-green-300">✓ Đã hoàn thành</span>
                @endif
                @unless ($lesson->is_published)
                    <span class="rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-700 dark:bg-stone-800 dark:text-stone-300">Bản nháp</span>
                @endunless
            </div>

            <p class="flex flex-wrap gap-x-5 text-sm text-stone-500 dark:text-stone-400">
                <span>{{ $lesson->words->count() }} từ vựng</span>
                @if ($lesson->duration_minutes)
                    <span>{{ $lesson->duration_minutes }} phút</span>
                @endif
                @if ($attachments->isNotEmpty())
                    <span>{{ $attachments->count() }} tài liệu</span>
                @endif
            </p>
        </div>
    </div>

    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-4">
        <div class="flex min-w-0 flex-col gap-10 lg:col-span-3">
            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Video --}}
            @if ($lesson->youtube_embed_url)
                <div class="aspect-video overflow-hidden rounded-2xl bg-stone-900 shadow-sm">
                    <iframe src="{{ $lesson->youtube_embed_url }}" title="{{ $lesson->title }}" class="size-full" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            @elseif ($lesson->video_url)
                <video src="{{ $lesson->video_url }}" controls class="aspect-video w-full rounded-2xl bg-stone-900"></video>
            @endif

            {{-- Content --}}
            @if ($lesson->content)
                <section class="flex flex-col gap-4">
                    <h2 class="text-xl font-bold tracking-tight">Nội dung bài học</h2>
                    {{-- Content is HTML written by admins in the admin panel. --}}
                    <div class="lesson-content rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900">
                        {!! $lesson->content !!}
                    </div>
                </section>
            @endif

            {{-- Vocabulary --}}
            @if ($lesson->words->isNotEmpty())
                <section id="vocabulary" class="flex scroll-mt-24 flex-col gap-4">
                    <div class="flex items-end justify-between gap-4">
                        <h2 class="text-xl font-bold tracking-tight">Từ vựng</h2>
                        <span class="text-sm text-stone-500 dark:text-stone-400">Bấm 🔊 để nghe phát âm</span>
                    </div>

                    <ol class="flex flex-col gap-3">
                        @foreach ($lesson->words as $word)
                            <li class="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-4 sm:flex-row sm:p-5 dark:border-stone-800 dark:bg-stone-900">
                                <div class="flex shrink-0 items-center gap-3 sm:w-44 sm:flex-col sm:items-center sm:justify-center sm:gap-1 sm:border-r sm:border-stone-200 sm:pr-5 sm:text-center dark:sm:border-stone-800">
                                    @if ($word->image_src)
                                        <img src="{{ $word->image_src }}" alt="" class="size-14 rounded-lg object-cover">
                                    @endif
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

                                    @if ($word->pivot->note || $word->note)
                                        <p class="text-sm text-stone-600 dark:text-stone-400">💡 {{ $word->pivot->note ?: $word->note }}</p>
                                    @endif

                                    @if (! empty($word->examples))
                                        <ul class="mt-1 flex flex-col gap-2 border-t border-stone-100 pt-3 dark:border-stone-800">
                                            @foreach ($word->examples as $example)
                                                <li class="flex items-start gap-2">
                                                    <button
                                                        type="button"
                                                        data-speak="{{ $example['sentence'] }}"
                                                        @if (! empty($example['audio_url'])) data-audio="{{ \App\Models\Word::audioUrl($example['audio_url']) }}" @endif
                                                        class="mt-0.5 shrink-0 text-stone-400 transition hover:text-red-600"
                                                        aria-label="Nghe câu ví dụ"
                                                    >
                                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                                                    </button>
                                                    <div class="flex flex-col text-sm">
                                                        <span class="text-base">{{ $example['sentence'] }}</span>
                                                        @if (! empty($example['pinyin']))
                                                            <span class="text-red-600/80 dark:text-red-400/80">{{ $example['pinyin'] }}</span>
                                                        @endif
                                                        @if (! empty($example['meaning']))
                                                            <span class="text-stone-500 dark:text-stone-400">{{ $example['meaning'] }}</span>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            {{-- Attachments --}}
            @if ($attachments->isNotEmpty())
                <section class="flex flex-col gap-4">
                    <h2 class="text-xl font-bold tracking-tight">Tài liệu đính kèm</h2>

                    <ul class="divide-y divide-stone-200 overflow-hidden rounded-2xl border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                        @foreach ($attachments as $attachment)
                            @php $downloadUrl = route('lessons.attachments.download', [$course, $lesson, $attachment['index']]); @endphp
                            <li>
                                @if ($attachment['is_audio'])
                                    <div class="flex flex-col gap-3 p-4">
                                        <div class="flex items-center gap-4">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300">
                                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z" /></svg>
                                            </span>
                                            <div class="flex min-w-0 flex-1 flex-col">
                                                <span class="truncate font-medium">{{ $attachment['name'] }}</span>
                                                @if ($attachment['size'] !== null)
                                                    <span class="text-xs text-stone-500 dark:text-stone-400">{{ Number::fileSize($attachment['size'], precision: 1) }}</span>
                                                @endif
                                            </div>
                                            <a href="{{ $downloadUrl }}" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-stone-400 transition hover:bg-stone-100 hover:text-stone-700 dark:hover:bg-stone-800 dark:hover:text-stone-200" title="Tải xuống" aria-label="Tải xuống {{ $attachment['name'] }}">
                                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                            </a>
                                        </div>
                                        <audio controls preload="metadata" controlslist="nodownload" src="{{ route('lessons.attachments.stream', [$course, $lesson, $attachment['index']]) }}" class="w-full">
                                            Trình duyệt của bạn không hỗ trợ phát âm thanh.
                                        </audio>
                                    </div>
                                @else
                                    <a href="{{ $downloadUrl }}" class="flex items-center gap-4 p-4 transition hover:bg-stone-50 dark:hover:bg-stone-800">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-xs font-bold uppercase text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ Str::limit($attachment['extension'] ?: 'file', 4, '') }}
                                        </span>
                                        <div class="flex min-w-0 flex-1 flex-col">
                                            <span class="truncate font-medium">{{ $attachment['name'] }}</span>
                                            @if ($attachment['size'] !== null)
                                                <span class="text-xs text-stone-500 dark:text-stone-400">{{ Number::fileSize($attachment['size'], precision: 1) }}</span>
                                            @endif
                                        </div>
                                        <svg class="size-5 shrink-0 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-label="Tải xuống"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Complete + navigation --}}
            <div class="flex flex-col gap-4 border-t border-stone-200 pt-8 dark:border-stone-800">
                <a href="{{ route('lessons.review', [$course, $lesson, 1]) }}" class="flex items-center justify-center gap-2 rounded-lg border-2 border-red-600 px-4 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                    Ôn tập bài học
                </a>

                @auth
                    @unless ($isCompleted)
                        @if ($canComplete)
                            <form method="POST" action="{{ route('lessons.complete', [$course, $lesson]) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                                    ✓ Hoàn thành bài học{{ $next ? ' & học bài tiếp theo' : '' }}
                                </button>
                            </form>
                        @else
                            <p class="rounded-lg bg-stone-100 px-4 py-3 text-center text-sm text-stone-600 dark:bg-stone-800 dark:text-stone-300">
                                {{ \App\Support\LessonReview::completionRequirement() }}
                            </p>
                        @endif
                    @endunless
                @else
                    <p class="rounded-lg bg-amber-50 px-4 py-3 text-center text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        Đây là bài học thử. <a href="{{ route('login') }}" class="font-semibold underline">Đăng nhập</a> để lưu tiến độ và học toàn bộ khoá học.
                    </p>
                @endauth

                <div class="flex items-stretch justify-between gap-4">
                    @if ($previous)
                        <a href="{{ route('lessons.show', [$course, $previous]) }}" class="flex max-w-[48%] flex-col rounded-lg border border-stone-200 px-4 py-3 text-sm transition hover:border-red-300 dark:border-stone-800">
                            <span class="text-xs text-stone-500">← Bài trước</span>
                            <span class="truncate font-medium">{{ $previous->title }}</span>
                        </a>
                    @else
                        <span></span>
                    @endif

                    @if ($next)
                        <a href="{{ route('lessons.show', [$course, $next]) }}" class="flex max-w-[48%] flex-col items-end rounded-lg border border-stone-200 px-4 py-3 text-right text-sm transition hover:border-red-300 dark:border-stone-800">
                            <span class="text-xs text-stone-500">Bài tiếp →</span>
                            <span class="truncate font-medium">{{ $next->title }}</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Lesson list --}}
        <aside class="lg:col-span-1">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 lg:sticky lg:top-24 dark:border-stone-800 dark:bg-stone-900">
                <a href="{{ route('courses.show', $course) }}" class="mb-3 block text-sm font-semibold hover:text-red-600">{{ $course->title }}</a>

                @if ($lessons->isNotEmpty())
                    <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800">
                        <div class="h-full rounded-full bg-green-600" style="width: {{ round($completedLessonIds->count() / $lessons->count() * 100) }}%"></div>
                    </div>
                @endif

                <ol class="flex flex-col gap-1">
                    @foreach ($lessons as $item)
                        @php
                            $current = $item->is($lesson);
                            $done = $completedLessonIds->contains($item->id);
                            $open = $isEnrolled || $item->is_free || auth()->user()?->isAdmin();
                        @endphp
                        <li>
                            <a
                                href="{{ $open ? route('lessons.show', [$course, $item]) : route('courses.show', $course) }}"
                                @class([
                                    'flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition',
                                    'bg-red-50 font-semibold text-red-700 dark:bg-red-950 dark:text-red-300' => $current,
                                    'hover:bg-stone-100 dark:hover:bg-stone-800' => ! $current,
                                    'text-stone-400' => ! $open,
                                ])
                                @if ($current) aria-current="page" @endif
                            >
                                <span @class([
                                    'flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                    'bg-green-600 text-white' => $done,
                                    'bg-stone-200 text-stone-600 dark:bg-stone-700 dark:text-stone-300' => ! $done,
                                ])>{{ $done ? '✓' : $loop->iteration }}</span>
                                <span class="truncate">{{ $item->title }}</span>
                                @unless ($open)
                                    <span class="ml-auto text-xs" aria-label="Cần đăng ký">🔒</span>
                                @endunless
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>
</x-layouts.app>
