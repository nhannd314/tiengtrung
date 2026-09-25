@props(['course'])

<a href="{{ route('courses.show', $course) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-stone-800 dark:bg-stone-900">
    <div class="relative aspect-video overflow-hidden">
        @if ($course->thumbnail_url)
            <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="size-full object-cover transition group-hover:scale-105">
        @else
            <div class="flex size-full items-center justify-center bg-linear-to-br from-red-600 to-amber-500">
                <span class="text-6xl font-bold text-white/90">学</span>
            </div>
        @endif

        @if ($course->hsk_level)
            <span class="absolute top-3 left-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-red-700 shadow-sm">
                HSK {{ $course->hsk_level }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <h3 class="text-lg font-semibold leading-snug group-hover:text-red-600">{{ $course->title }}</h3>

        @if ($course->description)
            <p class="line-clamp-2 text-sm text-stone-600 dark:text-stone-400">{{ $course->description }}</p>
        @endif

        <div class="mt-auto flex items-center gap-2 pt-2 text-sm text-stone-500 dark:text-stone-400">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
            </svg>
            {{ $course->lessons_count }} bài học
        </div>
    </div>
</a>
