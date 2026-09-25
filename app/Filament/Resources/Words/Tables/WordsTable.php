<?php

namespace App\Filament\Resources\Words\Tables;

use App\Models\Word;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->disk(Word::IMAGE_DISK)
                    ->toggleable(),
                TextColumn::make('hanzi')
                    ->searchable()
                    ->sortable()
                    ->size('lg'),
                TextColumn::make('pinyin')
                    ->searchable(['pinyin', 'pinyin_number']),
                TextColumn::make('meaning_text')
                    ->label('Meanings')
                    ->wrap()
                    ->limit(60)
                    // meanings is a JSON list stored with unescaped unicode, so LIKE matches Vietnamese text.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('meanings', 'like', "%{$search}%")),
                TextColumn::make('han_viet')
                    ->label('Hán Việt')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('part_of_speech_labels')
                    ->label('Part of speech')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('hsk_level')
                    ->label('HSK')
                    ->sortable(),
                TextColumn::make('examples')
                    ->label('Examples')
                    ->state(fn (Word $record): int => count($record->examples ?? []))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('hsk_level')
                    ->label('HSK level')
                    ->options(array_combine(range(1, 9), array_map(fn (int $level) => "HSK {$level}", range(1, 9)))),
                SelectFilter::make('part_of_speech')
                    ->options(Word::PARTS_OF_SPEECH)
                    // Each meaning is stored as ["<part_of_speech>", "<meaning>"].
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, string $partOfSpeech): Builder => $query->where('meanings', 'like', '%["'.$partOfSpeech.'",%'),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
