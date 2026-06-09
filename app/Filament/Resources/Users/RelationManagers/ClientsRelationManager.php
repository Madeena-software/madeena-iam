<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\UserStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('client_app_user_id')
                    ->label('Client App User ID')
                    ->maxLength(255),
                Select::make('status')
                    ->options(UserStatus::class)
                    ->required(),
                Toggle::make('is_blocked')
                    ->label('Is Blocked')
                    ->required(),
                DateTimePicker::make('approved_at')
                    ->disabled(),
                Select::make('approved_by')
                    ->relationship('approvedBy', 'name')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pivot.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        UserStatus::APPROVED => 'success',
                        UserStatus::PENDING_APPROVAL => 'warning',
                        UserStatus::SUSPENDED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('pivot.is_blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('pivot.client_app_user_id')
                    ->label('App User ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pivot.approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('pivot.approvedBy.name')
                    ->label('Approved By')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('attach')
                    ->label('Attach Oauth Client')
                    ->modalHeading('Attach Oauth Client')
                    ->form([
                        TextInput::make('client_id')
                            ->label('Client ID')
                            ->placeholder('Enter Client ID (UUID)')
                            ->required()
                            ->uuid()
                            ->rules(['exists:oauth_clients,id'])
                            ->validationMessages([
                                'exists' => 'The Client ID is invalid or does not exist.',
                            ]),
                        Select::make('status')
                            ->options(UserStatus::class)
                            ->default(UserStatus::PENDING_APPROVAL)
                            ->required(),
                        Toggle::make('is_blocked')
                            ->default(false)
                            ->required(),
                        TextInput::make('client_app_user_id')
                            ->label('Client App User ID'),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        $user = $livewire->getOwnerRecord();

                        if ($user->clients()->where('client_id', $data['client_id'])->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Client is already attached to this user.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $user->clients()->attach($data['client_id'], [
                            'status' => $data['status'],
                            'is_blocked' => $data['is_blocked'],
                            'client_app_user_id' => $data['client_app_user_id'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Client attached successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
