<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * role and is_active are not mass assignable (so sign-ups can't set them), but admins may set them here.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;
        $user->forceFill($data)->save();

        return $user;
    }
}
