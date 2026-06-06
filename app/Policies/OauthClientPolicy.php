<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OauthClient;
use Illuminate\Auth\Access\HandlesAuthorization;

class OauthClientPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OauthClient');
    }

    public function view(AuthUser $authUser, OauthClient $oauthClient): bool
    {
        return $authUser->can('View:OauthClient');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OauthClient');
    }

    public function update(AuthUser $authUser, OauthClient $oauthClient): bool
    {
        return $authUser->can('Update:OauthClient');
    }

    public function delete(AuthUser $authUser, OauthClient $oauthClient): bool
    {
        return $authUser->can('Delete:OauthClient');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OauthClient');
    }

    public function restore(AuthUser $authUser, OauthClient $oauthClient): bool
    {
        return $authUser->can('Restore:OauthClient');
    }

    public function forceDelete(AuthUser $authUser, OauthClient $oauthClient): bool
    {
        return $authUser->can('ForceDelete:OauthClient');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OauthClient');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OauthClient');
    }

    public function replicate(AuthUser $authUser, OauthClient $oauthClient): bool
    {
        return $authUser->can('Replicate:OauthClient');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OauthClient');
    }

}