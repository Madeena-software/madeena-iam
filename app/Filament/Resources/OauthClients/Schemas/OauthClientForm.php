<?php

namespace App\Filament\Resources\OauthClients\Schemas;

use App\Models\OauthClient;
use App\Models\Owner;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OauthClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label('Client ID')
                    ->placeholder('Auto-generated UUID if left blank')
                    ->readOnly(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(true)
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->rules(['nullable', 'uuid'])
                    ->suffixAction(
                        Action::make('copy_client_id')
                            ->label('Copy Client ID')
                            ->icon('heroicon-o-clipboard-document')
                            ->alpineClickHandler('window.navigator.clipboard.writeText($wire.get(\'data.id\'))')
                            ->action(fn () => null),
                    ),
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
                Hidden::make('is_secret_revealed')
                    ->dehydrated(false)
                    ->default(false),
                TextInput::make('secret')
                    ->label('Client Secret')
                    ->placeholder(fn (string $operation): bool => $operation === 'create' ? 'Auto-generated 40-character string if left blank' : '')
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn ($state) => $state !== '••••••••••••••••••••••••••••••••••••••••' && filled($state))
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->password(fn (Get $get) => ! $get('is_secret_revealed'))
                    ->formatStateUsing(fn ($state) => '••••••••••••••••••••••••••••••••••••••••')
                    ->suffixActions([
                        Action::make('reveal_secret')
                            ->icon('heroicon-o-eye')
                            ->hidden(fn (Get $get) => $get('is_secret_revealed'))
                            ->modalHeading('Confirm Password')
                            ->modalDescription('Please confirm your password to view the client secret.')
                            ->form([
                                TextInput::make('user_password')
                                    ->label('Your Password')
                                    ->password()
                                    ->required()
                                    ->rules([
                                        fn () => function (string $attribute, $value, \Closure $fail) {
                                            /** @var User $user */
                                            $user = request()->user();
                                            if (! Hash::check($value, $user->password)) {
                                                $fail('Incorrect password.');
                                            }
                                        },
                                    ]),
                            ])
                            ->action(function (Get $get, Set $set, $record) {
                                if ($record) {
                                    $set('secret', $record->secret);
                                    $set('is_secret_revealed', true);
                                }
                            }),
                        Action::make('copy_secret')
                            ->icon('heroicon-o-clipboard-document')
                            ->visible(fn (Get $get) => $get('is_secret_revealed'))
                            ->alpineClickHandler('window.navigator.clipboard.writeText($wire.get(\'data.secret\'))')
                            ->action(fn () => null),
                    ]),
                Textarea::make('redirect_uris')
                    ->required()
                    ->helperText('Enter one or more redirect URIs separated by commas. Must be valid HTTPS URLs unless using localhost or the configured allowed IP.')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : array_map('trim', explode(',', $state)))
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $uris = is_array($value) ? $value : array_map('trim', explode(',', $value));
                                $allowedHttpIp = config('app.allowed_http_ip');
                                foreach ($uris as $uri) {
                                    if (! filter_var($uri, FILTER_VALIDATE_URL)) {
                                        $fail("The URI {$uri} is invalid.");
                                    } elseif (! str_starts_with($uri, 'https://') && ! str_starts_with($uri, 'http://localhost') && ! str_starts_with($uri, 'http://127.0.0.1') && ($allowedHttpIp ? ! str_starts_with($uri, "http://{$allowedHttpIp}") : true)) {
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
                TextEntry::make('app_logo_preview')
                    ->label('Current App Logo')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(function (?OauthClient $record) {
                        if (empty($record?->app_logo_path)) {
                            return 'No logo uploaded.';
                        }
                        /** @var FilesystemAdapter $disk */
                        $disk = Storage::disk('public');

                        return new HtmlString('<img src="'.$disk->url($record->app_logo_path).'" alt="App Logo" style="max-height: 100px; border-radius: 8px; border: 1px solid #374151;">');
                    }),
                FileUpload::make('app_logo_path')
                    ->label('Upload App Logo')
                    ->disk('public')
                    ->directory('logos')
                    ->visibility('public')
                    ->rules(['nullable', 'image', 'max:2048']),
                TextInput::make('description'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('provider')
                    ->default('users')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->disabled(),
                TextEntry::make('created_by')
                    ->label('Created By')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(fn (?OauthClient $record) => $record?->creator?->name ?? '-'),
                TextEntry::make('updated_by')
                    ->label('Updated By')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(fn (?OauthClient $record) => $record?->updater?->name ?? '-'),
                TextEntry::make('deleted_by')
                    ->label('Deleted By')
                    ->hidden(fn (string $operation, ?OauthClient $record): bool => $operation === 'create' || ! $record?->deleted_by)
                    ->state(fn (?OauthClient $record) => $record?->deleter?->name ?? '-'),
                TextEntry::make('created_at')
                    ->label('Created At')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(fn (?OauthClient $record) => $record?->created_at?->translatedFormat('d-M-Y H:i:s') ?? '-'),
                TextEntry::make('updated_at')
                    ->label('Updated At')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(fn (?OauthClient $record) => $record?->updated_at?->translatedFormat('d-M-Y H:i:s') ?? '-'),
                TextEntry::make('deleted_at')
                    ->label('Deleted At')
                    ->hidden(fn (string $operation, ?OauthClient $record): bool => ! $record?->deleted_at)
                    ->state(fn (?OauthClient $record) => $record?->deleted_at?->translatedFormat('d-M-Y H:i:s') ?? '-'),
            ]);
    }
}
