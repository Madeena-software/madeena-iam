<?php

namespace App\Filament\Resources\AuthenticationLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuthenticationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('authenticatable_id')
                    ->label('User')
                    ->formatStateUsing(fn ($record) => $record->authenticatable?->name ?? $record->authenticatable_id)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('authenticatable_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('login_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('login_successful')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('logout_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('cleared_by_user')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('login_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Read-only resource, no bulk actions.
            ]);
    }
}
