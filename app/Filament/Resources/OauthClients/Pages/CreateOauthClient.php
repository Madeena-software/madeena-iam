<?php

declare(strict_types=1);

namespace App\Filament\Resources\OauthClients\Pages;

use App\Filament\Resources\OauthClients\OauthClientResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateOauthClient extends CreateRecord
{
    protected static string $resource = OauthClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['id'])) {
            $data['id'] = (string) Str::uuid();
        }

        if (empty($data['secret'])) {
            $data['secret'] = Str::random(40);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $client = $this->record;
        $plainSecret = $client->plainSecret ?? $this->data['secret'] ?? null;

        if ($plainSecret) {
            Notification::make()
                ->title('OAuth Client Credentials Created')
                ->warning()
                ->persistent()
                ->body("Please copy the client credentials now. The secret will not be displayed again.\n\n**Client ID:** `{$client->id}`\n\n**Client Secret:** `{$plainSecret}`")
                ->send();
        }
    }
}
