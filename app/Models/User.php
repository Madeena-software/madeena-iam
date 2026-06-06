<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Enums\UserStatus;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUuids, SoftDeletes, HasApiTokens, HasRoles, LogsActivity;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['password', 'remember_token']);
    }

    public function authentications()
    {
        return $this->morphMany(AuthenticationLog::class, 'authenticatable')->orderByDesc('login_at');
    }

    public function clients()
    {
        return $this->belongsToMany(OauthClient::class, 'client_user', 'user_id', 'client_id')
                    ->using(ClientUser::class)
                    ->withPivot(['status', 'approved_at', 'approved_by', 'is_blocked', 'client_app_user_id'])
                    ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('super_admin');
    }
}
