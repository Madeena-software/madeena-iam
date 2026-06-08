<?php

namespace App\Filament\Resources\OauthClients\Pages;

use App\Filament\Resources\OauthClients\OauthClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOauthClients extends ListRecords
{
    protected static string $resource = OauthClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['owner'])
            ->leftJoin('users as creators', 'oauth_clients.created_by', '=', 'creators.id')
            ->leftJoin('users as updaters', 'oauth_clients.updated_by', '=', 'updaters.id')
            ->leftJoin('users as deleters', 'oauth_clients.deleted_by', '=', 'deleters.id')
            ->select('oauth_clients.*', 'creators.name as creator_name', 'updaters.name as updater_name', 'deleters.name as deleter_name');
    }
}
