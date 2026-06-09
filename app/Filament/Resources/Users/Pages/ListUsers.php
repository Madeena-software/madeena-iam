<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->leftJoin('users as creators', 'users.created_by', '=', 'creators.id')
            ->leftJoin('users as updaters', 'users.updated_by', '=', 'updaters.id')
            ->leftJoin('users as deleters', 'users.deleted_by', '=', 'deleters.id')
            ->select('users.*', 'creators.name as creator_name', 'updaters.name as updater_name', 'deleters.name as deleter_name');
    }
}
