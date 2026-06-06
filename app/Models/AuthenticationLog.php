<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthenticationLog extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'location' => 'json',
        'login_successful' => 'boolean',
        'cleared_by_user' => 'boolean',
    ];

    public function authenticatable()
    {
        return $this->morphTo();
    }
}
