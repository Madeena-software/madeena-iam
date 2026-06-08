<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Session;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Session');
    }

    public function view(AuthUser $authUser, Session $session): bool
    {
        return $authUser->can('View:Session');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Session $session): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, Session $session): bool
    {
        return $authUser->can('Delete:Session');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Session');
    }
}
