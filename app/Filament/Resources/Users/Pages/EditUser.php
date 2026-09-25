<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Deleting your own account from here would lock you out.
            DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->is(auth()->user())),
        ];
    }

    /**
     * role and is_active are not mass assignable (so sign-ups can't set them), but admins may change them here.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        return $record;
    }
}
