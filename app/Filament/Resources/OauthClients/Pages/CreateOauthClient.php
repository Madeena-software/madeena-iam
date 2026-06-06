<?php

namespace App\Filament\Resources\OauthClients\Pages;

use App\Filament\Resources\OauthClients\OauthClientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOauthClient extends CreateRecord
{
    protected static string $resource = OauthClientResource::class;
}
