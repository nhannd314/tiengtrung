<?php

namespace App\Filament\Resources\Words\Schemas;

use App\Models\Word;
use App\Support\Pinyin;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class WordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(2)
                    ->components([
                        Section::make('Word')
                            ->columns(2)
                            ->components([
                                TextInput::make('hanzi')
                                    ->label('Hanzi (simplified)')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('traditional')
                                    ->label('Traditional')
                                    ->maxLength(50),
                                TextInput::make('pinyin')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('nǐ hǎo')
                                    // Polyphonic characters (行 xíng / háng) are separate words: hanzi + pinyin_number is unique.
                                    ->rule(fn (Get $get, ?Word $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                        $pinyinNumber = Pinyin::toNumbered((string) $value);

                                        if ($pinyinNumber === null) {
                                            $fail('Enter valid tone-marked pinyin, e.g. nǐ hǎo.');

                                            return;
                                        }

                                        $exists = Word::query()
                                            ->where('hanzi', $get('hanzi'))
                                            ->where('pinyin_number', $pinyinNumber)
                                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                            ->exists();

                                        if ($exists) {
                                            $fail('This word (hanzi + pinyin) already exists.');
                                        }
                                    }),
                                // Derived from the tone-marked pinyin (used for search and text to speech).
                                Hidden::make('pinyin_number')
                                    ->dehydrateStateUsing(fn (Get $get): ?string => Pinyin::toNumbered((string) $get('pinyin'))),
                                TextInput::make('han_viet')
                                    ->label('Hán Việt')
                                    ->maxLength(100),
                            ]),
                        Section::make('Meanings')
                            ->description('The first meaning is shown first. All meanings are shown together and used in the review quizzes.')
                            ->components([
                                Repeater::make('meanings')
                                    ->hiddenLabel()
                                    ->schema([
                                        Select::make('part_of_speech')
                                            ->options(Word::PARTS_OF_SPEECH)
                                            ->native(false),
                                        TextInput::make('meaning')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                    ])
                                    ->columns(3)
                                    ->required()
                                    ->minItems(1)
                                    ->defaultItems(1)
                                    ->reorderable()
                                    ->addActionLabel('Add meaning')
                                    // The model stores [part_of_speech, meaning] pairs; the form edits them as named fields.
                                    ->afterStateHydrated(function (Repeater $component, ?array $state): void {
                                        $component->state(array_map(
                                            fn (array $item): array => array_is_list($item) ? ['part_of_speech' => $item[0], 'meaning' => $item[1]] : $item,
                                            $state ?? [],
                                        ));
                                        $component->hydrateItems();
                                    })
                                    ->dehydrateStateUsing(fn (?array $state): array => array_map(
                                        fn (array $item): array => [$item['part_of_speech'] ?? null, $item['meaning'] ?? ''],
                                        array_values($state ?? []),
                                    )),
                            ]),
                        Section::make('Examples')
                            ->components([
                                Repeater::make('examples')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextInput::make('sentence')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        TextInput::make('pinyin')
                                            ->maxLength(255),
                                        TextInput::make('meaning')
                                            ->maxLength(255),
                                        FileUpload::make('audio_url')
                                            ->label('Audio')
                                            ->acceptedFileTypes(Word::AUDIO_FILE_TYPES)
                                            ->disk(Word::AUDIO_DISK)
                                            ->directory(Word::EXAMPLE_AUDIO_DIRECTORY)
                                            ->visibility('public')
                                            ->maxSize(5120)
                                            ->helperText('Leave empty to have Azure text to speech read it (generated on the queue shortly after saving).')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['sentence'] ?? null)
                                    ->addActionLabel('Add example')
                                    // Store a plain JSON list, not an object keyed by Filament's item ids.
                                    ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? [])),
                            ]),
                    ]),
                Section::make('Settings')
                    ->columnSpan(1)
                    ->components([
                        Select::make('hsk_level')
                            ->label('HSK level')
                            ->options(array_combine(range(1, 9), array_map(fn (int $level) => "HSK {$level}", range(1, 9))))
                            ->native(false),
                        TextInput::make('stroke_count')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(255),
                        FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->imageEditor()
                            ->disk(Word::IMAGE_DISK)
                            ->directory('words')
                            ->visibility('public')
                            ->maxSize(2048),
                        FileUpload::make('audio_url')
                            ->label('Audio')
                            ->acceptedFileTypes(Word::AUDIO_FILE_TYPES)
                            ->disk(Word::AUDIO_DISK)
                            ->directory(Word::AUDIO_DIRECTORY)
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText('Leave empty to have Azure text to speech read it (generated on the queue shortly after saving).'),
                        Textarea::make('note')
                            ->rows(3),
                    ]),
            ]);
    }
}
