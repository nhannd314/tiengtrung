<?php

// Only the rules used by the app are translated; others fall back to English.
return [
    'boolean' => ':Attribute không hợp lệ.',
    'confirmed' => ':Attribute xác nhận không khớp.',
    'current_password' => 'Mật khẩu hiện tại không đúng.',
    'different' => ':Attribute phải khác :other.',
    'email' => ':Attribute không đúng định dạng email.',
    'image' => ':Attribute phải là hình ảnh.',
    'lowercase' => ':Attribute phải viết thường.',
    'max' => [
        'file' => ':Attribute không được lớn hơn :max KB.',
        'string' => ':Attribute không được vượt quá :max ký tự.',
    ],
    'mimes' => ':Attribute phải có định dạng: :values.',
    'min' => [
        'string' => ':Attribute phải có ít nhất :min ký tự.',
    ],
    'password' => [
        'letters' => ':Attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':Attribute phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => ':Attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':Attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':Attribute này đã bị lộ trong một vụ rò rỉ dữ liệu. Vui lòng chọn :attribute khác.',
    ],
    'regex' => ':Attribute không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.',
    'string' => ':Attribute phải là chuỗi ký tự.',
    'uploaded' => 'Không tải lên được :attribute.',
    'unique' => ':Attribute này đã được sử dụng.',
];
