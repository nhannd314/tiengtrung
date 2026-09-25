<x-layouts.app title="Trang chủ">
    <section class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-14 sm:px-6 lg:py-20">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">你好 · Xin chào</p>
            <h1 class="max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">Học tiếng Trung từ con số 0 đến HSK</h1>
            <p class="max-w-2xl text-stone-600 dark:text-stone-400">
                Các khoá học bài bản theo cấp độ HSK, kèm từ vựng có pinyin, âm Hán Việt, câu ví dụ và tài liệu đính kèm.
            </p>
        </div>
    </section>

    <section id="courses" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-12 sm:px-6">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Khoá học</h2>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ $courses->count() }} khoá học đang mở</p>
            </div>
        </div>

        @if ($courses->isEmpty())
            <div class="rounded-2xl border border-dashed border-stone-300 p-12 text-center text-stone-500 dark:border-stone-700 dark:text-stone-400">
                Chưa có khoá học nào. Vui lòng quay lại sau!
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <x-course-card :course="$course" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
