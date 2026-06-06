<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientUser extends Pivot
{
    use SoftDeletes;

    protected $table = 'client_user';

    protected $casts = [
        'status' => UserStatus::class,
        'approved_at' => 'datetime',
        'is_blocked' => 'boolean',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(OauthClient::class, 'client_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
