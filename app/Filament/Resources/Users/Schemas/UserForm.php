<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextEntry::make('email_verified_at')
                    ->label('Email verified at')
                    ->state(fn (?User $record) => $record?->email_verified_at?->format('Y-m-d H:i:s') ?? '-')
                    ->hidden(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                TextEntry::make('created_by')
                    ->label('Created By')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(fn (?User $record) => $record?->creator?->name ?? '-'),
                TextEntry::make('updated_by')
                    ->label('Updated By')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->state(fn (?User $record) => $record?->updater?->name ?? '-'),
                TextEntry::make('deleted_by')
                    ->label('Deleted By')
                    ->hidden(fn (string $operation, ?User $record): bool => $operation === 'create' || ! $record?->deleted_by)
                    ->state(fn (?User $record) => $record?->deleter?->name ?? '-'),
            ]);
    }
}
