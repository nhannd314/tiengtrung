<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;

class LessonAttachmentController extends Controller
{
    /**
     * Download the attachment at $index of lessons.attachments. Files are served through here,
     * not by public URL, so lesson access rules apply.
     */
    public function download(Request $request, Course $course, Lesson $lesson, int $index): StreamedResponse
    {
        $file = $this->findFile($request, $course, $lesson, $index);

        return Storage::disk(Lesson::ATTACHMENTS_DISK)->download($file['path'], $file['name']);
    }

    /**
     * Serve an audio attachment inline for the lesson page's <audio> player. BinaryFileResponse
     * answers Range requests, which browsers need to seek. Only audio is served inline so other
     * uploads (HTML, SVG, ...) can never render on this origin.
     */
    public function stream(Request $request, Course $course, Lesson $lesson, int $index): BinaryFileResponse
    {
        $file = $this->findFile($request, $course, $lesson, $index);
        abort_unless($file['is_audio'], 404);

        return response()->file(
            Storage::disk(Lesson::ATTACHMENTS_DISK)->path($file['path']),
            [
                'Content-Type' => MimeTypes::getDefault()->getMimeTypes($file['extension'])[0] ?? 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ],
        )->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file['name'], 'audio.'.$file['extension']);
    }

    /**
     * @return array{index: int, path: string, name: string, extension: string, size: ?int, is_audio: bool}
     */
    private function findFile(Request $request, Course $course, Lesson $lesson, int $index): array
    {
        $lesson->setRelation('course', $course);
        abort_unless($lesson->isAccessibleBy($request->user()), 403);

        $file = $lesson->attachmentFiles()->get($index);
        abort_unless($file && Storage::disk(Lesson::ATTACHMENTS_DISK)->exists($file['path']), 404);

        return $file;
    }
}
