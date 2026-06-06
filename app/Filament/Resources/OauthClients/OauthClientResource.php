<?php

namespace App\Filament\Resources\OauthClients;

use App\Filament\Resources\OauthClients\Pages\CreateOauthClient;
use App\Filament\Resources\OauthClients\Pages\EditOauthClient;
use App\Filament\Resources\OauthClients\Pages\ListOauthClients;
use App\Filament\Resources\OauthClients\Schemas\OauthClientForm;
use App\Filament\Resources\OauthClients\Tables\OauthClientsTable;
use App\Models\OauthClient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OauthClientResource extends Resource
{
    protected static ?string $model = OauthClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OauthClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OauthClientsTable::configure($table);
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
            'index' => ListOauthClients::route('/'),
            'create' => CreateOauthClient::route('/create'),
            'edit' => EditOauthClient::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
