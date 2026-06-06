<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\Client as PassportClient;

class OauthClient extends PassportClient
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'grant_types' => 'array',
        'scopes' => 'array',
        'redirect_uris' => 'array',
        'personal_access_client' => 'bool',
        'password_client' => 'bool',
        'revoked' => 'bool',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
        });
        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
        static::deleting(function ($model) {
            $model->deleted_by = auth()->id();
            $model->saveQuietly();
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'client_user', 'client_id', 'user_id')
            ->using(ClientUser::class)
            ->withPivot(['status', 'approved_at', 'approved_by', 'is_blocked', 'client_app_user_id'])
            ->withTimestamps();
    }
}
