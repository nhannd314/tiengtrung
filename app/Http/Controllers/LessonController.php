<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonReviewRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonReviewResult;
use App\Models\Word;
use App\Support\LessonReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): View|RedirectResponse
    {
        $user = $request->user();

        if ($denied = $this->denyAccess($request, $course, $lesson)) {
            return $denied;
        }

        $user?->recordLessonView($lesson);

        $lesson->load('words');

        $lessons = $course->lessons()->published()->get(['id', 'course_id', 'title', 'slug', 'sort_order', 'is_free']);
        $position = $lessons->search(fn (Lesson $item) => $item->is($lesson));

        $completedLessonIds = $user
            ? $user->lessonProgress()
                ->whereIn('lessons.id', $lessons->pluck('id'))
                ->wherePivotNotNull('completed_at')
                ->pluck('lessons.id')
            : collect();

        return view('lessons.show', [
            'course' => $course,
            'lesson' => $lesson,
            'lessons' => $lessons,
            'previous' => $position === false ? null : $lessons->get($position - 1),
            'next' => $position === false ? null : $lessons->get($position + 1),
            'completedLessonIds' => $completedLessonIds,
            'isEnrolled' => (bool) $user?->isEnrolledIn($course),
            'canComplete' => (bool) $user?->canCompleteLesson($lesson),
        ]);
    }

    /**
     * One page per review part: review/1, review/2, ...
     */
    public function review(Request $request, Course $course, Lesson $lesson, int $part): View|RedirectResponse
    {
        abort_unless(LessonReview::exists($part), 404);

        if ($denied = $this->denyAccess($request, $course, $lesson)) {
            return $denied;
        }

        $lesson->load('words');

        // Each attempt at the speaking part starts without old pronunciation scores.
        if (LessonReview::PARTS[$part]['input'] === 'speech') {
            $request->session()->forget(LessonReview::pronunciationSessionKey($lesson));
        }

        return view('lessons.review', [
            'course' => $course,
            'lesson' => $lesson,
            'part' => $part,
            'questions' => (new LessonReview($lesson))->questions($part),
            'nextPart' => $part < LessonReview::TOTAL_PARTS ? $part + 1 : null,
            ...$this->reviewStats($request, $lesson),
        ]);
    }

    /**
     * Save a finished part, overwriting the user's previous result for that part.
     */
    public function storeReview(StoreLessonReviewRequest $request, Course $course, Lesson $lesson, int $part): JsonResponse
    {
        abort_unless(LessonReview::exists($part), 404);

        $lesson->setRelation('course', $course);
        abort_unless($lesson->isAccessibleBy($request->user()), 403);

        $lesson->load('words');
        $graded = (new LessonReview($lesson))->grade(
            $part,
            $request->validated('answers'),
            $request->session()->get(LessonReview::pronunciationSessionKey($lesson), []),
        );

        $result = LessonReviewResult::updateOrCreate(
            ['user_id' => $request->user()->id, 'lesson_id' => $lesson->id, 'part' => $part],
            [
                'correct_count' => $graded['correct'],
                'total_questions' => $graded['total'],
                'duration_seconds' => $request->validated('duration_seconds'),
                'mistakes' => $graded['mistakes'],
                'completed_at' => now(),
            ],
        );

        return response()->json([
            'correct_count' => $result->correct_count,
            'total_questions' => $result->total_questions,
            'duration' => $result->formattedDuration(),
            // Refreshed sidebar, so the page doesn't need a reload.
            'stats_html' => view('lessons.partials.review-stats', $this->reviewStats($request, $lesson))->render(),
        ]);
    }

    /**
     * Data for the review stats sidebar (resources/views/lessons/partials/review-stats.blade.php).
     *
     * @return array{results: Collection<int, LessonReviewResult>, mistakeWords: Collection<int, Word>}
     */
    private function reviewStats(Request $request, Lesson $lesson): array
    {
        $results = $request->user()
            ? $request->user()->lessonReviewResults()->where('lesson_id', $lesson->id)->get()->keyBy('part')
            : collect();

        return [
            'results' => $results,
            'mistakeWords' => $lesson->words->whereIn('id', $results->pluck('mistakes')->flatten()->unique())->values(),
        ];
    }

    public function complete(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $user = $request->user();

        abort_unless($lesson->isAccessibleBy($user), 403);

        if (! $user->canCompleteLesson($lesson)) {
            return redirect()->route('lessons.show', [$course, $lesson])
                ->with('status', LessonReview::completionRequirement());
        }

        $user->completeLesson($lesson);

        $next = $course->lessons()->published()->where('sort_order', '>', $lesson->sort_order)->first();

        if ($next && $next->isAccessibleBy($user)) {
            return redirect()->route('lessons.show', [$course, $next])
                ->with('status', "Bạn đã hoàn thành bài \"{$lesson->title}\". Tiếp tục nào!");
        }

        return redirect()->route('courses.show', $course)
            ->with('status', $next
                ? "Bạn đã hoàn thành bài \"{$lesson->title}\". Đăng ký khoá học để học tiếp nhé!"
                : 'Chúc mừng! Bạn đã học đến bài cuối cùng của khoá học. 太棒了!');
    }

    /**
     * 404 for drafts; login (guests) or back to the course (not enrolled) for locked lessons.
     */
    private function denyAccess(Request $request, Course $course, Lesson $lesson): ?RedirectResponse
    {
        $user = $request->user();
        $lesson->setRelation('course', $course);

        abort_unless($lesson->isVisibleTo($user), 404);

        if ($lesson->isAccessibleBy($user)) {
            return null;
        }

        return $user
            ? redirect()->route('courses.show', $course)->with('error', 'Bạn cần đăng ký khoá học để học bài này.')
            : redirect()->guest(route('login'));
    }
}
