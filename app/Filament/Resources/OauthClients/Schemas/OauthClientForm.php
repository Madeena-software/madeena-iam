<?php

namespace App\Filament\Resources\OauthClients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OauthClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('owner_type'),
                TextInput::make('owner_id')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('secret'),
                TextInput::make('provider'),
                Textarea::make('redirect_uris')
                    ->required()
                    ->helperText('Enter one or more redirect URIs separated by commas. Must be valid HTTPS URLs unless using localhost.')
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $uris = array_map('trim', explode(',', $value));
                                foreach ($uris as $uri) {
                                    if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                                        $fail("The URI {$uri} is invalid.");
                                    } elseif (!str_starts_with($uri, 'https://') && !str_starts_with($uri, 'http://localhost') && !str_starts_with($uri, 'http://127.0.0.1')) {
                                        $fail("The URI {$uri} must use HTTPS.");
                                    }
                                }
                            };
                        },
                    ])
                    ->columnSpanFull(),
                Textarea::make('grant_types')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('revoked')
                    ->required(),
                TextInput::make('app_logo_path'),
                TextInput::make('description'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('created_by'),
                TextInput::make('updated_by'),
                TextInput::make('deleted_by'),
            ]);
    }
}
