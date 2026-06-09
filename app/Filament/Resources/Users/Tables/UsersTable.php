<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(query: fn($query, $search) => $query->where('users.name', 'like', "%{$search}%"))
                    ->sortable(query: fn($query, $direction) => $query->orderBy('users.name', $direction)),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(query: fn($query, $search) => $query->where('users.email', 'like', "%{$search}%"))
                    ->sortable(query: fn($query, $direction) => $query->orderBy('users.email', $direction)),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(query: fn($query, $direction) => $query->orderBy('users.email_verified_at', $direction)),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(query: fn($query, $direction) => $query->orderBy('users.created_at', $direction)),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(query: fn($query, $direction) => $query->orderBy('users.updated_at', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator_name')
                    ->label('Created by')
                    ->searchable(query: fn($query, $search) => $query->where('creators.name', 'like', "%{$search}%"))
                    ->sortable(query: fn($query, $direction) => $query->orderBy('creators.name', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updater_name')
                    ->label('Updated by')
                    ->searchable(query: fn($query, $search) => $query->where('updaters.name', 'like', "%{$search}%"))
                    ->sortable(query: fn($query, $direction) => $query->orderBy('updaters.name', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable(query: fn($query, $direction) => $query->orderBy('users.deleted_at', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleter_name')
                    ->label('Deleted by')
                    ->searchable(query: fn($query, $search) => $query->where('deleters.name', 'like', "%{$search}%"))
                    ->sortable(query: fn($query, $direction) => $query->orderBy('deleters.name', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('users.created_at', 'desc')
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
