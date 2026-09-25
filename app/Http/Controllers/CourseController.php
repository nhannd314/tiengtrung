<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Word;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function show(Request $request, Course $course): View
    {
        $user = $request->user();

        // Admins can preview unpublished courses.
        abort_unless($course->is_published || $user?->isAdmin(), 404);

        $course->load(['lessons' => fn ($query) => $query->published()->withCount('words')]);
        $lessonIds = $course->lessons->pluck('id');

        $vocabulary = Word::whereHas('lessons', fn ($query) => $query->whereIn('lessons.id', $lessonIds));

        $enrollment = $user?->enrolledCourses()->whereKey($course->id)->first()?->pivot;

        $completedLessonIds = $user
            ? $user->lessonProgress()
                ->whereIn('lessons.id', $lessonIds)
                ->wherePivotNotNull('completed_at')
                ->pluck('lessons.id')
            : collect();

        return view('courses.show', [
            'course' => $course,
            'wordCount' => (clone $vocabulary)->count(),
            'previewWords' => $vocabulary->orderBy('id')->limit(12)->get(),
            'totalMinutes' => $course->lessons->sum('duration_minutes'),
            'enrollment' => $enrollment,
            'completedLessonIds' => $completedLessonIds,
        ]);
    }
}
