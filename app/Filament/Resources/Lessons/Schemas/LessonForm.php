<?php

namespace App\Filament\Resources\Lessons\Schemas;

use App\Models\Lesson;
use App\Models\Word;
use App\Support\LessonVocabulary;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Left column (2/3): details and attachments stacked, so Settings stays beside them on the right.
                Group::make()
                    ->columnSpan(2)
                    ->components([
                        Section::make('Lesson details')
                            ->components([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                // The HasSlug trait generates the slug from the title when left blank on create.
                                TextInput::make('slug')
                                    ->required(fn (string $operation): bool => $operation === 'edit')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->unique(
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('course_id', $get('course_id')),
                                    )
                                    ->validationMessages(['unique' => 'This slug is already used by another lesson in this course.'])
                                    ->helperText(fn (string $operation): string => $operation === 'create'
                                        ? 'Leave blank to generate it from the title.'
                                        : 'Lowercase letters, numbers and hyphens. Changing it breaks existing links.'),
                                Textarea::make('summary')
                                    ->rows(3),
                                RichEditor::make('content'),
                                TextInput::make('video_url')
                                    ->label('Video URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->helperText('YouTube links are embedded in the lesson page.'),
                            ]),
                        Section::make('Vocabulary')
                            ->description('One word per line: hanzi|pinyin|Hán Việt|meaning|meaning... A meaning may start with its part of speech, e.g. "noun:". Words are added to the dictionary, or updated there.')
                            ->components([
                                self::vocabularyTextarea(),
                            ]),
                        Section::make('Attachments')
                            ->components([
                                self::attachmentsUpload(),
                            ]),
                    ]),
                Section::make('Settings')
                    ->columnSpan(1)
                    ->components([
                        Select::make('course_id')
                            ->relationship('course', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label('Duration')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->suffix('minutes'),
                        TextInput::make('sort_order')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_free')
                            ->label('Free preview')
                            ->helperText('Free lessons can be studied without enrolling.')
                            ->default(false),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (bool $state, Get $get, Set $set): void {
                                if ($state && blank($get('published_at'))) {
                                    $set('published_at', now()->toDateTimeString());
                                }
                            }),
                        DateTimePicker::make('published_at')
                            ->label('Published at'),
                    ]),
            ]);
    }

    /**
     * The lesson's words as text (see LessonVocabulary). Not a lessons column: it is saved as the lesson's word list.
     */
    private static function vocabularyTextarea(): Textarea
    {
        return Textarea::make('vocabulary')
            ->hiddenLabel()
            ->rows(10)
            ->placeholder('研究|yánjiū|nghiên cứu|noun:nghiên cứu|verb:tìm hiểu, nghiên cứu')
            ->helperText('Parts of speech: '.implode(', ', array_keys(Word::PARTS_OF_SPEECH)).'. Hán Việt may be left empty: 研究|yánjiū||noun:nghiên cứu. A line with only the hanzi (e.g. 研究) takes the word from the dictionary; hanzi not found there are removed.')
            ->formatStateUsing(fn (?Lesson $record): string => $record ? LessonVocabulary::format($record->words) : '')
            // A line with only hanzi is filled in from the dictionary when leaving the field, or dropped if not found.
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set): void {
                $expanded = LessonVocabulary::expand($state);
                if ($expanded !== (string) $state) {
                    $set('vocabulary', $expanded);
                }
            })
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        LessonVocabulary::parse(LessonVocabulary::expand($value));
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ])
            ->dehydrated(false)
            ->saveRelationshipsUsing(fn (Lesson $record, ?string $state) => LessonVocabulary::sync($record, $state));
    }

    /**
     * Files for lessons.attachments, on the private disk (downloaded through LessonAttachmentController).
     * Each file goes into its own folder under its original name, so the stored path alone
     * keeps the name learners see when downloading: lesson-attachments/{ulid}/Bài 1.pdf
     */
    private static function attachmentsUpload(): FileUpload
    {
        return FileUpload::make('attachments')
            ->hiddenLabel()
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->downloadable()
            ->disk(Lesson::ATTACHMENTS_DISK)
            ->directory('lesson-attachments')
            ->visibility('private')
            ->maxFiles(10)
            // Must stay under Livewire's temporary upload limit (12 MB) and PHP's upload_max_filesize.
            ->maxSize(10 * 1024)
            ->acceptedFileTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'text/plain',
                'audio/*',
                'image/*',
            ])
            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::ulid().'/'.self::safeFileName($file->getClientOriginalName()))
            // Items saved as {"path": ..., "name": ...} are turned into plain paths, which is what FileUpload works with.
            // (formatStateUsing() would replace FileUpload's own hydration hook, so do it here and call it afterwards.)
            ->afterStateHydrated(function (FileUpload $component, mixed $state): void {
                $component->state(collect(Arr::wrap($state))
                    ->map(fn (string|array $item) => is_array($item) ? ($item['path'] ?? null) : $item)
                    ->filter()
                    ->all());
                $component->hydrateFiles();
            })
            ->helperText('PDF, Word, PowerPoint, Excel, audio, images or ZIP. Up to 10 files, 10 MB each. Drag to reorder.');
    }

    /**
     * Keep the original name readable but safe as a single path segment.
     */
    private static function safeFileName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $name), ' .-');

        return $name !== '' ? Str::limit($name, 150, '') : 'file';
    }
}
