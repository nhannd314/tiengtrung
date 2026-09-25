<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonReviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'answers' => ['required', 'array'],
            'answers.*.word_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
