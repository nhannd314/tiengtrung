<?php

namespace App\Http\Requests\Profile;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The email is the account's identity and can't be changed here, so it isn't validated or saved.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/', Rule::unique('users', 'phone')->ignore($this->user())],
            'address' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Số điện thoại không hợp lệ (ví dụ: 0912345678).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'phone' => 'số điện thoại',
            'address' => 'địa chỉ',
            'avatar' => 'ảnh đại diện',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize($this->input('phone')),
            'remove_avatar' => $this->boolean('remove_avatar'),
        ]);
    }
}
