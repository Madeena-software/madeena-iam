<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case SUSPENDED = 'suspended';
}
