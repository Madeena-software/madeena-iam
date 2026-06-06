<?php

namespace App\Filament\Resources\AuthenticationLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->searchable(),
                TextColumn::make('authenticatable_type')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->searchable(),
                TextColumn::make('login_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('login_successful')
                    ->boolean(),
                TextColumn::make('logout_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('cleared_by_user')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
