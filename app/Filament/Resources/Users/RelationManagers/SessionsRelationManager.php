<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Session;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip_address')
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('device_details.description')
                    ->label('Device')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('user_agent', 'like', "%{$search}%");
                    }),
                TextColumn::make('last_activity')
                    ->label('Last Activity')
                    ->formatStateUsing(fn ($state) => Carbon::createFromTimestamp($state)->diffForHumans())
                    ->tooltip(fn ($state) => Carbon::createFromTimestamp($state)->toDateTimeString())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Terminate Session')
                    ->modalDescription(fn (Session $record) => $record->id === session()->getId()
                        ? 'Warning: You are terminating your current active session. Doing so will log you out immediately. Are you sure you want to proceed?'
                        : 'Are you sure you want to terminate this active device session?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Terminate Selected Sessions')
                        ->modalDescription('Are you sure you want to terminate the selected active device sessions? If your own active session is selected, you will be logged out instantly.'),
                ]),
            ]);
    }
}
