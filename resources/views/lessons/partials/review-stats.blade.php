{{-- Review stats sidebar. Also rendered by LessonController::storeReview to refresh the page after saving. --}}
@php
    use App\Models\LessonReviewResult;
    use App\Support\LessonReview;

    $correct = $results->sum('correct_count');
    $total = $results->sum('total_questions');
    $accuracy = $total > 0 ? (int) round($correct / $total * 100) : 0;
@endphp

<div class="flex flex-col gap-5 rounded-2xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
    <div>
        <h2 class="font-bold">Thống kê ôn tập</h2>
        @if ($results->isNotEmpty())
            <p class="text-xs text-stone-500 dark:text-stone-400">Cập nhật lần cuối · {{ $results->max('completed_at')->format('H:i d/m/Y') }}</p>
        @endif
    </div>

    @guest
        <p class="text-sm text-stone-600 dark:text-stone-400">
            <a href="{{ route('login') }}" class="font-semibold text-red-600 hover:underline">Đăng nhập</a> để lưu và xem thống kê kết quả ôn tập.
        </p>
    @else
        @if ($results->isEmpty())
            <p class="text-sm text-stone-600 dark:text-stone-400">
                Bạn chưa hoàn thành phần ôn tập nào. Làm xong một phần để lưu kết quả.
            </p>
        @else
            {{-- Overall, across finished parts --}}
            <div class="flex items-center gap-4">
                <div
                    class="relative flex size-20 shrink-0 items-center justify-center rounded-full"
                    style="background: conic-gradient(var(--color-red-600) {{ $accuracy }}%, var(--color-stone-200) 0)"
                    role="img"
                    aria-label="Đúng {{ $accuracy }}%"
                >
                    <span class="flex size-16 items-center justify-center rounded-full bg-white text-lg font-bold dark:bg-stone-900">{{ $accuracy }}%</span>
                </div>

                <dl class="grid flex-1 gap-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-stone-500 dark:text-stone-400">Số câu đúng</dt>
                        <dd class="font-semibold tabular-nums">{{ $correct }}/{{ $total }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-stone-500 dark:text-stone-400">Tổng thời gian</dt>
                        <dd class="font-semibold tabular-nums">{{ LessonReviewResult::formatSeconds($results->sum('duration_seconds')) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-stone-500 dark:text-stone-400">Đã làm</dt>
                        <dd class="font-semibold tabular-nums">{{ $results->count() }}/{{ count(LessonReview::PARTS) }} phần</dd>
                    </div>
                </dl>
            </div>

            {{-- Per part --}}
            <ul class="flex flex-col divide-y divide-stone-100 border-t border-stone-100 text-sm dark:divide-stone-800 dark:border-stone-800">
                @foreach (range(1, LessonReview::TOTAL_PARTS) as $number)
                    @php($result = $results->get($number))
                    <li class="flex items-center gap-3 py-2">
                        <span class="w-14 shrink-0 font-medium">Phần {{ $number }}</span>
                        @if ($result)
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800">
                                <div @class([
                                    'h-full rounded-full',
                                    'bg-green-500' => $result->correct_count === $result->total_questions,
                                    'bg-red-500' => $result->correct_count !== $result->total_questions,
                                ]) style="width: {{ $result->accuracy() }}%"></div>
                            </div>
                            <span class="w-10 text-right tabular-nums">{{ $result->correct_count }}/{{ $result->total_questions }}</span>
                            <span class="w-10 text-right text-stone-500 tabular-nums dark:text-stone-400">{{ $result->formattedDuration() }}</span>
                        @else
                            <span class="flex-1 text-stone-400">{{ LessonReview::exists($number) ? 'Chưa làm' : 'Sắp ra mắt' }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- Mistakes, across finished parts --}}
            @if ($mistakeWords->isNotEmpty())
                <div class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold">Từ cần xem lại ({{ $mistakeWords->count() }})</h3>
                    <ul class="flex flex-col gap-1.5">
                        @foreach ($mistakeWords as $word)
                            <li class="flex items-center gap-2 rounded-lg bg-red-50 px-3 py-1.5 text-sm dark:bg-red-950/50">
                                <span class="text-lg">{{ $word->hanzi }}</span>
                                <span class="text-red-600 dark:text-red-400">{{ $word->pinyin }}</span>
                                <span class="truncate text-stone-600 dark:text-stone-400">{{ $word->meaning_text }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-300">🏆 Không sai từ nào. Tuyệt vời!</p>
            @endif
        @endif
    @endguest
</div>
