<?php

use App\Support\Pinyin;

test('tone-marked pinyin is converted to tone numbers', function (string $pinyin, ?string $numbered) {
    expect(Pinyin::toNumbered($pinyin))->toBe($numbered);
})->with([
    ['yánjiū', 'yan2jiu1'],
    ['xièxie', 'xie4xie5'],
    ['Zhōngguó', 'zhong1guo2'],
    ['nǐ hǎo', 'ni3hao3'],
    ['lǜchá', 'lü4cha2'],
    ['péngyou', 'peng2you5'],
    // Spelling rules: a final isn't followed by a vowel; an apostrophe marks the other split.
    ['xīnán', 'xi1nan2'],
    ["xī'ān", 'xi1an1'],
    ["fāng'àn", 'fang1an4'],
    ['abcxyz', null],
    ['hǎǒ', null],
    ['', null],
]);
