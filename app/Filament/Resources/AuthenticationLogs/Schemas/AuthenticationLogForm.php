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
                    ->required(),
                TextInput::make('authenticatable_type')
                    ->required(),
                TextInput::make('ip_address'),
                Textarea::make('user_agent')
                    ->columnSpanFull(),
                DateTimePicker::make('login_at'),
                Toggle::make('login_successful')
                    ->required(),
                DateTimePicker::make('logout_at'),
                Toggle::make('cleared_by_user')
                    ->required(),
                TextInput::make('location'),
            ]);
    }
}
