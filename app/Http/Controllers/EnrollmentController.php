<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_unless($course->is_published, 404);

        $request->user()->enrolledCourses()->syncWithoutDetaching([
            $course->id => ['enrolled_at' => now()],
        ]);

        return redirect()->route('courses.show', $course)
            ->with('status', 'Bạn đã đăng ký khoá học thành công. Chúc bạn học tốt! 加油!');
    }
}
