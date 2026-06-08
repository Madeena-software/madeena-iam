<?php

namespace App\Filament\Resources\AuthenticationLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AuthenticationLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                 TextInput::make('authenticatable_id')
                    ->label('User')
                    ->formatStateUsing(fn ($record) => $record?->authenticatable?->name ?? $record?->authenticatable_id),
                TextInput::make('authenticatable_type'),
                TextInput::make('ip_address'),
                Textarea::make('user_agent')
                    ->columnSpanFull(),
                DateTimePicker::make('login_at'),
                Toggle::make('login_successful')
                    ->required(),
                DateTimePicker::make('logout_at'),
                Toggle::make('cleared_by_user')
                    ->required(),
                Textarea::make('location')
                    ->columnSpanFull()
                    ->rows(4)
                    ->formatStateUsing(fn ($state) => is_array($state) || is_object($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $state),
            ]);
    }
}
