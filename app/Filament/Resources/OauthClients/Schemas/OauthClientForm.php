<?php

namespace App\Filament\Resources\OauthClients\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Owner;
use Filament\Schemas\Schema;

class OauthClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label('Client ID')
                    ->placeholder('Auto-generated UUID if left blank')
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(true)
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->rules(['nullable', 'uuid']),
                TextInput::make('name')
                    ->required(),
                Select::make('owner_type')
                    ->label('Owner Type')
                    ->options([
                        'Company' => 'Company',
                        'Individual' => 'Individual',
                        'Developer' => 'Developer',
                    ])
                    ->default('Company')
                    ->searchable()
                    ->live()
                    ->required(),
                Select::make('owner_id')
                    ->label('Owner Name')
                    ->options(fn (Get $get) => Owner::where('type', $get('owner_type') ?? 'Company')->pluck('name', 'id'))
                    ->default(fn () => Owner::where('name', 'PT Madeena Karya Indonesia')->first()?->id)
                    ->searchable()
                    ->required(),
                TextInput::make('secret')
                    ->label('Client Secret')
                    ->placeholder(fn (string $operation): bool => $operation === 'create' ? 'Auto-generated 40-character string if left blank' : '')
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->password()
                    ->revealable(),
                Textarea::make('redirect_uris')
                    ->required()
                    ->helperText('Enter one or more redirect URIs separated by commas. Must be valid HTTPS URLs unless using localhost.')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : array_map('trim', explode(',', $state)))
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $uris = is_array($value) ? $value : array_map('trim', explode(',', $value));
                                foreach ($uris as $uri) {
                                    if (! filter_var($uri, FILTER_VALIDATE_URL)) {
                                        $fail("The URI {$uri} is invalid.");
                                    } elseif (! str_starts_with($uri, 'https://') && ! str_starts_with($uri, 'http://localhost') && ! str_starts_with($uri, 'http://127.0.0.1')) {
                                        $fail("The URI {$uri} must use HTTPS.");
                                    }
                                }
                            };
                        },
                    ])
                    ->columnSpanFull(),
                CheckboxList::make('grant_types')
                    ->required()
                    ->options([
                        'authorization_code' => 'Authorization Code',
                        'refresh_token' => 'Refresh Token',
                        'client_credentials' => 'Client Credentials',
                        'password' => 'Password',
                    ])
                    ->descriptions([
                        'authorization_code' => 'Web apps redirecting users via standard browser login. Example: Redirects user to the central login screen, then sends them back after authentication. Use Case: Integrated standard web applications like Simama, Madeena ERP, or Madeena Workspace.',
                        'refresh_token' => 'Allowing apps to retrieve new access tokens silently without prompting users again. Example: Automatically refreshing session credentials in the background before they expire. Use Case: Any client application requiring persistent login/session continuity.',
                        'client_credentials' => 'Machine-to-machine sync scripts (no user authentication). Example: Server-to-server daemon using Client ID and Secret directly to get a token. Use Case: Background cron jobs syncing databases between Madeena IAM and external IT tools.',
                        'password' => 'Trusted native or mobile applications (direct username/password exchange). Example: User typing credentials directly into native mobile inputs rather than a web redirect. Use Case: First-party iOS/Android native mobile applications.',
                    ])
                    ->columnSpanFull(),
                Toggle::make('revoked')
                    ->required(),
                FileUpload::make('app_logo_path')
                    ->label('App Logo')
                    ->disk('s3')
                    ->directory('logos')
                    ->visibility('public')
                    ->rules(['nullable', 'image', 'max:2048']),
                TextInput::make('description'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
