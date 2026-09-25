<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Lessons of the HSK 1 course: [title, summary, vocabulary hanzi].
     *
     * @var list<array{string, string, list<string>}>
     */
    protected const LESSONS = [
        ['Chào hỏi', 'Những câu chào hỏi cơ bản và đại từ nhân xưng.', ['你好', '你', '我', '他', '她', '好', '很', '谢谢', '再见']],
        ['Giới thiệu bản thân', 'Hỏi tên, giới thiệu nghề nghiệp và quốc tịch.', ['叫', '什么', '名字', '是', '不', '人', '中国', '老师', '学生', '朋友', '汉语', '学习', '看', '书']],
        ['Gia đình', 'Nói về các thành viên trong gia đình và thú cưng.', ['家', '爸爸', '妈妈', '爱', '狗', '猫']],
        ['Ăn uống', 'Gọi món và nói về đồ ăn thức uống.', ['吃', '喝', '水', '茶', '米饭']],
        ['Số đếm và thời gian', 'Đếm số từ 1 đến 10 và nói về ngày.', ['一', '二', '三', '十', '今天', '明天']],
    ];

    /**
     * Seed a sample HSK 1 course. Requires HskWordSeeder to have run.
     */
    public function run(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $course = Course::updateOrCreate(
            ['title' => 'Tiếng Trung sơ cấp HSK 1'],
            [
                'description' => 'Khoá học dành cho người mới bắt đầu, bao gồm từ vựng và mẫu câu HSK 1.',
                'hsk_level' => 1,
                'is_published' => true,
                'published_at' => now(),
                'created_by' => $admin?->id,
            ],
        );

        $words = Word::where('hsk_level', 1)->pluck('id', 'hanzi');

        foreach (self::LESSONS as $index => [$title, $summary, $vocabulary]) {
            $lesson = $course->lessons()->updateOrCreate(
                ['title' => $title],
                [
                    'summary' => $summary,
                    'content' => "<h2>{$title}</h2><p>{$summary}</p>",
                    'duration_minutes' => 30,
                    'sort_order' => $index + 1,
                    'is_free' => $index === 0,
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );

            $lesson->words()->sync(
                collect($vocabulary)
                    ->filter(fn (string $hanzi) => $words->has($hanzi))
                    ->values()
                    ->mapWithKeys(fn (string $hanzi, int $order) => [$words[$hanzi] => ['sort_order' => $order + 1]])
                    ->all()
            );
        }
    }
}
