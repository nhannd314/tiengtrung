<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(2)
                    ->components([
                        Section::make('Account')
                            ->columns(2)
                            ->components([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->dehydrateStateUsing(fn (string $state): string => mb_strtolower(trim($state))),
                                // Same rules as the sign-up form: stored as 0xxxxxxxxx, unique, used to log in.
                                TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->placeholder('0912345678')
                                    ->rules([fn (?User $record): Closure => self::phoneRule($record)])
                                    ->dehydrateStateUsing(fn (string $state): string => PhoneNumber::normalize($state)),
                                Textarea::make('address')
                                    ->rows(2)
                                    ->maxLength(255),
                            ]),
                        Section::make('Password')
                            ->description(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null)
                            ->columns(2)
                            ->components([
                                TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->rule(Password::defaults())
                                    ->confirmed()
                                    // Blank on edit = keep the current password. The model hashes it (cast "hashed").
                                    ->dehydrated(fn (?string $state): bool => filled($state)),
                                TextInput::make('password_confirmation')
                                    ->label('Confirm password')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(false),
                            ]),
                    ]),
                Section::make('Settings')
                    ->columnSpan(1)
                    ->components([
                        FileUpload::make('avatar')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->disk(User::AVATAR_DISK)
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(2048),
                        // Admins can't change their own role or deactivate themselves, so they can't lock themselves out.
                        Select::make('role')
                            ->options([
                                User::ROLE_USER => 'User',
                                User::ROLE_ADMIN => 'Admin',
                            ])
                            ->required()
                            ->native(false)
                            ->default(User::ROLE_USER)
                            ->disabled(fn (?User $record): bool => self::isCurrentUser($record))
                            ->helperText('Admins can open this admin panel.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => self::isCurrentUser($record))
                            ->helperText('Inactive accounts cannot log in. New sign-ups stay inactive until activated here.'),
                    ]),
            ]);
    }

    /**
     * Validate the phone the way it will be stored (normalised), including uniqueness.
     */
    private static function phoneRule(?User $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $phone = PhoneNumber::normalize((string) $value);

            if (! preg_match('/^0\d{9}$/', $phone)) {
                $fail('The phone number is not valid (e.g. 0912345678).');

                return;
            }

            if (User::where('phone', $phone)->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))->exists()) {
                $fail('This phone number is already used by another account.');
            }
        };
    }

    private static function isCurrentUser(?User $record): bool
    {
        return $record !== null && $record->is(auth()->user());
    }
}
