<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;

class HskWordSeeder extends Seeder
{
    /**
     * HSK 1 words: [hanzi, pinyin, pinyin_number, han_viet, meanings as [part_of_speech, meaning] pairs].
     *
     * @var list<array{string, string, string, string, list<array{string, string}>}>
     */
    protected const WORDS = [
        ['你好', 'nǐ hǎo', 'ni3hao3', 'nễ hảo', [['phrase', 'xin chào']]],
        ['你', 'nǐ', 'ni3', 'nễ', [['pronoun', 'bạn, anh, chị (ngôi thứ hai)']]],
        ['我', 'wǒ', 'wo3', 'ngã', [['pronoun', 'tôi, mình']]],
        ['他', 'tā', 'ta1', 'tha', [['pronoun', 'anh ấy, ông ấy']]],
        ['她', 'tā', 'ta1', 'tha', [['pronoun', 'cô ấy, chị ấy']]],
        ['好', 'hǎo', 'hao3', 'hảo', [['adjective', 'tốt'], ['adjective', 'khoẻ'], ['adjective', 'hay'], ['adverb', 'rất, thật (nhấn mạnh)']]],
        ['很', 'hěn', 'hen3', 'ngận', [['adverb', 'rất']]],
        ['谢谢', 'xièxie', 'xie4xie5', 'tạ tạ', [['verb', 'cảm ơn']]],
        ['再见', 'zàijiàn', 'zai4jian4', 'tái kiến', [['verb', 'tạm biệt']]],
        ['是', 'shì', 'shi4', 'thị', [['verb', 'là'], ['interjection', 'vâng, đúng']]],
        ['不', 'bù', 'bu4', 'bất', [['adverb', 'không']]],
        ['人', 'rén', 'ren2', 'nhân', [['noun', 'người']]],
        ['中国', 'Zhōngguó', 'zhong1guo2', 'Trung Quốc', [['noun', 'Trung Quốc']]],
        ['汉语', 'Hànyǔ', 'han4yu3', 'Hán ngữ', [['noun', 'tiếng Hán, tiếng Trung']]],
        ['学习', 'xuéxí', 'xue2xi2', 'học tập', [['verb', 'học, học tập']]],
        ['老师', 'lǎoshī', 'lao3shi1', 'lão sư', [['noun', 'thầy giáo, cô giáo']]],
        ['学生', 'xuésheng', 'xue2sheng5', 'học sinh', [['noun', 'học sinh']]],
        ['朋友', 'péngyou', 'peng2you5', 'bằng hữu', [['noun', 'bạn bè']]],
        ['叫', 'jiào', 'jiao4', 'khiếu', [['verb', 'gọi'], ['verb', 'tên là']]],
        ['什么', 'shénme', 'shen2me5', 'thập ma', [['pronoun', 'cái gì']]],
        ['名字', 'míngzi', 'ming2zi5', 'danh tự', [['noun', 'tên']]],
        ['看', 'kàn', 'kan4', 'khán', [['verb', 'nhìn, xem'], ['verb', 'đọc']]],
        ['书', 'shū', 'shu1', 'thư', [['noun', 'sách']]],
        ['家', 'jiā', 'jia1', 'gia', [['noun', 'nhà, gia đình'], ['measure_word', 'lượng từ cho cửa hàng, công ty']]],
        ['爸爸', 'bàba', 'ba4ba5', 'bá bá', [['noun', 'bố, ba']]],
        ['妈妈', 'māma', 'ma1ma5', 'ma ma', [['noun', 'mẹ']]],
        ['爱', 'ài', 'ai4', 'ái', [['verb', 'yêu'], ['verb', 'thích']]],
        ['狗', 'gǒu', 'gou3', 'cẩu', [['noun', 'con chó']]],
        ['猫', 'māo', 'mao1', 'miêu', [['noun', 'con mèo']]],
        ['吃', 'chī', 'chi1', 'ngật', [['verb', 'ăn']]],
        ['喝', 'hē', 'he1', 'hát', [['verb', 'uống']]],
        ['水', 'shuǐ', 'shui3', 'thuỷ', [['noun', 'nước']]],
        ['茶', 'chá', 'cha2', 'trà', [['noun', 'trà']]],
        ['米饭', 'mǐfàn', 'mi3fan4', 'mễ phạn', [['noun', 'cơm']]],
        ['一', 'yī', 'yi1', 'nhất', [['numeral', 'một']]],
        ['二', 'èr', 'er4', 'nhị', [['numeral', 'hai']]],
        ['三', 'sān', 'san1', 'tam', [['numeral', 'ba']]],
        ['十', 'shí', 'shi2', 'thập', [['numeral', 'mười']]],
        ['今天', 'jīntiān', 'jin1tian1', 'kim thiên', [['noun', 'hôm nay']]],
        ['明天', 'míngtiān', 'ming2tian1', 'minh thiên', [['noun', 'ngày mai']]],
    ];

    /**
     * Example sentences keyed by hanzi: [sentence, pinyin, meaning].
     *
     * @var array<string, list<array{string, string, string}>>
     */
    protected const EXAMPLES = [
        '你好' => [['你好，老师！', 'Nǐ hǎo, lǎoshī!', 'Em chào thầy/cô!']],
        '我' => [['我是学生。', 'Wǒ shì xuésheng.', 'Tôi là học sinh.']],
        '很' => [['我很好。', 'Wǒ hěn hǎo.', 'Tôi rất khoẻ.']],
        '谢谢' => [['谢谢你！', 'Xièxie nǐ!', 'Cảm ơn bạn!']],
        '叫' => [['你叫什么名字？', 'Nǐ jiào shénme míngzi?', 'Bạn tên là gì?']],
        '学习' => [['我学习汉语。', 'Wǒ xuéxí Hànyǔ.', 'Tôi học tiếng Trung.']],
        '爱' => [['我爱妈妈。', 'Wǒ ài māma.', 'Tôi yêu mẹ.']],
        '喝' => [['我喝茶。', 'Wǒ hē chá.', 'Tôi uống trà.']],
    ];

    /**
     * Seed HSK 1 vocabulary. Safe to run multiple times.
     */
    public function run(): void
    {
        foreach (self::WORDS as [$hanzi, $pinyin, $pinyinNumber, $hanViet, $meanings]) {
            $attributes = [
                'pinyin' => $pinyin,
                'han_viet' => $hanViet,
                'meanings' => $meanings,
                'hsk_level' => 1,
            ];

            // Only words with seeded examples get them set, so examples added in the admin are kept.
            if (isset(self::EXAMPLES[$hanzi])) {
                $attributes['examples'] = array_map(fn (array $example) => [
                    'sentence' => $example[0],
                    'pinyin' => $example[1],
                    'meaning' => $example[2],
                    'audio_url' => null,
                ], self::EXAMPLES[$hanzi]);
            }

            Word::updateOrCreate(['hanzi' => $hanzi, 'pinyin_number' => $pinyinNumber], $attributes);
        }
    }
}
