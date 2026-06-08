<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_id')
                    ->label('Subject')
                    ->formatStateUsing(fn($record) => $record->subject?->name ?? $record->subject_id)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('causer_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('causer_id')
                    ->label('Causer')
                    ->formatStateUsing(fn($record) => $record->causer?->name ?? $record->causer_id)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Read-only resource, no bulk actions.
            ]);
    }
}
