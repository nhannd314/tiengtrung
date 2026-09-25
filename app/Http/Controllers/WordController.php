<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WordController extends Controller
{
    /**
     * Search the dictionary by hanzi, pinyin (with or without tones), Hán Việt or Vietnamese meaning,
     * listing the published lessons that teach each word.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $words = $search === ''
            ? null
            : Word::query()
                ->tap(fn (Builder $query) => $this->search($query, mb_substr($search, 0, 50)))
                ->with(['lessons' => fn ($query) => $query->published()
                    ->whereHas('course', fn ($query) => $query->published())
                    ->with('course')
                    ->orderBy('sort_order')])
                ->orderByRaw('hanzi = ? desc', [$search])
                ->orderByRaw('length(hanzi)')
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString();

        return view('words.index', [
            'search' => $search,
            'words' => $words,
            'partsOfSpeech' => Word::PARTS_OF_SPEECH,
        ]);
    }

    /**
     * Pinyin is also matched without tone numbers and spaces, so "nihao" finds "ni3 hao3".
     *
     * @param  Builder<Word>  $query
     */
    private function search(Builder $query, string $search): void
    {
        $like = '%'.mb_strtolower($search).'%';
        $toneless = preg_replace('/[\s\d]+/', '', mb_strtolower($search));
        $tonelessPinyin = 'pinyin_number';

        foreach (['1', '2', '3', '4', '5', ' '] as $character) {
            $tonelessPinyin = "replace({$tonelessPinyin}, '{$character}', '')";
        }

        $query->where(fn (Builder $query) => $query
            ->where('hanzi', 'like', $like)
            ->orWhere('traditional', 'like', $like)
            ->orWhere('pinyin', 'like', $like)
            ->orWhere('pinyin_number', 'like', $like)
            ->orWhere('han_viet', 'like', $like)
            ->orWhereRaw('lower(meanings) like ?', [$like])
            ->when($toneless !== '', fn (Builder $query) => $query
                ->orWhereRaw("lower({$tonelessPinyin}) like ?", ['%'.$toneless.'%'])));
    }
}
