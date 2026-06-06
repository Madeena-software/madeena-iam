<?php

namespace App\Filament\Resources\AuthenticationLogs\Pages;

use App\Filament\Resources\AuthenticationLogs\AuthenticationLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuthenticationLog extends EditRecord
{
    protected static string $resource = AuthenticationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
