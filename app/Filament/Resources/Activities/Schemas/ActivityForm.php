<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('log_name'),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('subject_type'),
                TextInput::make('subject_id')
                    ->label('Subject')
                    ->formatStateUsing(fn ($record) => $record?->subject?->name ?? $record?->subject_id),
                TextInput::make('event'),
                TextInput::make('causer_type'),
                TextInput::make('causer_id')
                    ->label('Causer')
                    ->formatStateUsing(fn ($record) => $record?->causer?->name ?? $record?->causer_id),
                Textarea::make('attribute_changes')
                    ->columnSpanFull()
                    ->rows(8)
                    ->formatStateUsing(fn ($state) => is_array($state) || is_object($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $state),
                Textarea::make('properties')
                    ->columnSpanFull()
                    ->rows(8)
                    ->formatStateUsing(fn ($state) => is_array($state) || is_object($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $state),
            ]);
    }
}
