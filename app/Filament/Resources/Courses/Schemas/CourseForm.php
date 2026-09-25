<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Course details')
                    ->columnSpan(2)
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        // The HasSlug trait generates the slug from the title when left blank on create.
                        TextInput::make('slug')
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->maxLength(255)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true)
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'Leave blank to generate it from the title.'
                                : 'Lowercase letters, numbers and hyphens. Changing it breaks existing links.'),
                        Textarea::make('description')
                            ->rows(5),
                        FileUpload::make('thumbnail')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('courses')
                            ->maxSize(2048),
                    ]),
                Section::make('Settings')
                    ->columnSpan(1)
                    ->components([
                        Select::make('hsk_level')
                            ->label('HSK level')
                            ->options(collect(range(1, 9))->mapWithKeys(fn (int $level): array => [$level => "HSK {$level}"])),
                        TextInput::make('sort_order')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
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
}
