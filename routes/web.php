<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonAttachmentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewPronunciationController;
use App\Http\Controllers\WordController;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
Route::get('words', [WordController::class, 'index'])->name('words.index');
Route::post('courses/{course}/enroll', [EnrollmentController::class, 'store'])
    ->middleware('auth')
    ->name('courses.enroll');

Route::scopeBindings()->prefix('courses/{course}/lessons/{lesson:slug}')->group(function () {
    Route::get('/', [LessonController::class, 'show'])->name('lessons.show');
    Route::get('review', fn (Course $course, Lesson $lesson) => redirect()->route('lessons.review', [$course, $lesson, 1]));
    Route::get('review/{part}', [LessonController::class, 'review'])->whereNumber('part')->name('lessons.review');
    Route::post('review/{part}', [LessonController::class, 'storeReview'])->whereNumber('part')->middleware('auth')->name('lessons.review.store');
    // Azure bills per request, so pronunciation scoring needs a login and is rate limited.
    Route::post('review/pronunciation', ReviewPronunciationController::class)
        ->middleware(['auth', 'throttle:30,1'])
        ->name('lessons.review.pronunciation');
    Route::post('complete', [LessonController::class, 'complete'])->middleware('auth')->name('lessons.complete');
    Route::get('attachments/{index}', [LessonAttachmentController::class, 'download'])->whereNumber('index')->name('lessons.attachments.download');
    Route::get('attachments/{index}/stream', [LessonAttachmentController::class, 'stream'])->whereNumber('index')->name('lessons.attachments.stream');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->controller(ProfileController::class)->group(function () {
    Route::get('profile', 'edit')->name('profile.edit');
    Route::patch('profile', 'update')->name('profile.update');
    Route::put('profile/password', 'updatePassword')->name('profile.password.update');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
