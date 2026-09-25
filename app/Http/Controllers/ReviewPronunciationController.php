<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Services\AzurePronunciationAssessment;
use App\Support\LessonReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReviewPronunciationController extends Controller
{
    /**
     * Score one recorded word (review part 5) with Azure and remember the score in the session for grading.
     */
    public function __invoke(Request $request, Course $course, Lesson $lesson, AzurePronunciationAssessment $azure): JsonResponse
    {
        $lesson->setRelation('course', $course);
        abort_unless($lesson->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'word_id' => ['required', 'integer'],
            'audio' => ['required', 'file', 'max:2048', 'mimetypes:audio/wav,audio/x-wav,audio/wave,audio/vnd.wave'],
        ]);

        $word = $lesson->words()->whereKey($validated['word_id'])->first();
        abort_unless($word, 404);

        if (! $azure->isConfigured()) {
            return response()->json(['message' => 'Dịch vụ chấm phát âm chưa được cấu hình.'], 503);
        }

        try {
            $assessment = $azure->assess($request->file('audio')->get(), $word->hanzi);
        } catch (RuntimeException $e) {
            Log::warning($e->getMessage(), ['lesson_id' => $lesson->id, 'word_id' => $word->id]);

            return response()->json(['message' => 'Không chấm được phát âm lúc này. Vui lòng thử lại.'], 502);
        }

        $request->session()->put(LessonReview::pronunciationSessionKey($lesson).".{$word->id}", $assessment['score']);

        return response()->json([
            ...array_map(fn ($value) => is_float($value) ? round($value) : $value, $assessment),
            'passed' => $assessment['score'] >= LessonReview::PRONUNCIATION_PASS_SCORE,
        ]);
    }
}
