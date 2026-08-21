<?php

namespace App\Filament\Resources\OauthClients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OauthClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner_type')
                    ->label('Owner type')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner.name')
                    ->label('Owner name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable(query: fn ($query, $search) => $query->where('oauth_clients.name', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('oauth_clients.name', $direction)),
                TextColumn::make('provider')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('revoked')
                    ->boolean()
                    ->sortable(),
                ImageColumn::make('app_logo_path')
                    ->label('App Logo')
                    ->disk('public')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('oauth_clients.created_at', $direction)),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('oauth_clients.updated_at', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator_name')
                    ->label('Created by name')
                    ->searchable(query: fn ($query, $search) => $query->where('creators.name', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('creators.name', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updater_name')
                    ->label('Updated by name')
                    ->searchable(query: fn ($query, $search) => $query->where('updaters.name', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('updaters.name', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('oauth_clients.deleted_at', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleter_name')
                    ->label('Deleted by name')
                    ->searchable(query: fn ($query, $search) => $query->where('deleters.name', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('deleters.name', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('oauth_clients.created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
