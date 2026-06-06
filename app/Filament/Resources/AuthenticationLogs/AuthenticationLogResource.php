<?php

namespace App\Filament\Resources\AuthenticationLogs;

use App\Filament\Resources\AuthenticationLogs\Pages\CreateAuthenticationLog;
use App\Filament\Resources\AuthenticationLogs\Pages\EditAuthenticationLog;
use App\Filament\Resources\AuthenticationLogs\Pages\ListAuthenticationLogs;
use App\Filament\Resources\AuthenticationLogs\Schemas\AuthenticationLogForm;
use App\Filament\Resources\AuthenticationLogs\Tables\AuthenticationLogsTable;
use App\Models\AuthenticationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AuthenticationLogResource extends Resource
{
    protected static ?string $model = AuthenticationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AuthenticationLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthenticationLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthenticationLogs::route('/'),
            'create' => CreateAuthenticationLog::route('/create'),
            'edit' => EditAuthenticationLog::route('/{record}/edit'),
        ];
    }
}
