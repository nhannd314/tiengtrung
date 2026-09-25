<?php

use App\Models\Word;
use App\Support\TypedAnswer;

function typedWord(string $hanzi, string $pinyin): Word
{
    return new Word(['hanzi' => $hanzi, 'pinyin' => $pinyin]);
}

test('accepts the hanzi and pinyin with tones', function (string $input) {
    expect(TypedAnswer::matches($input, typedWord('你好', 'nǐ hǎo')))->toBeTrue();
})->with([
    'hanzi' => '你好',
    'hanzi with spaces' => ' 你 好 ',
    'tone marks' => 'nǐ hǎo',
    'tone marks, no space' => 'nǐhǎo',
    'uppercase' => 'NǏ HǍO',
    'tone numbers' => 'ni3hao3',
    'tone numbers with space' => 'ni3 hao3',
]);

test('rejects wrong words, wrong tones and pinyin without tones', function (string $input) {
    expect(TypedAnswer::matches($input, typedWord('你好', 'nǐ hǎo')))->toBeFalse();
})->with([
    'empty' => '',
    'spaces' => '   ',
    'other word' => '再见',
    'no tones' => 'nihao',
    'wrong tone' => 'ni2hao3',
    'tones swapped order' => 'ni3hao',
    'extra letters' => 'ni3hao3a',
]);

test('the neutral tone can be written as 5 or left out', function (string $input) {
    expect(TypedAnswer::matches($input, typedWord('谢谢', 'xièxie')))->toBeTrue();
})->with(['xièxie', 'xie4xie', 'xie4xie5']);

test('ü can be typed as ü or v', function (string $input) {
    expect(TypedAnswer::matches($input, typedWord('绿', 'lǜ')))->toBeTrue();
})->with(['lǜ', 'lü4', 'lv4']);

test('proper nouns are case insensitive', function () {
    expect(TypedAnswer::matches('zhong1guo2', typedWord('中国', 'Zhōngguó')))->toBeTrue();
});
