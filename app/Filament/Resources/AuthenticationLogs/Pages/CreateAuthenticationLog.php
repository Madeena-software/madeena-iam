<?php

namespace App\Filament\Resources\AuthenticationLogs\Pages;

use App\Filament\Resources\AuthenticationLogs\AuthenticationLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthenticationLog extends CreateRecord
{
    protected static string $resource = AuthenticationLogResource::class;
}
