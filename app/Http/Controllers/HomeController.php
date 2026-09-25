<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the home page with the list of published courses.
     */
    public function __invoke(): View
    {
        $courses = Course::published()
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->latest('published_at')
            ->get();

        return view('home', ['courses' => $courses]);
    }
}
