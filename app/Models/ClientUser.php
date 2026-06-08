<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Mail\OnboardingMail;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ClientUser extends Pivot
{
    use LogsActivity, SoftDeletes;

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
            if ($model->status === UserStatus::APPROVED) {
                $model->approved_at = now();
                $model->approved_by = auth()->id();
            }
        });
        static::created(function ($model) {
            if ($model->status === UserStatus::APPROVED) {
                $model->sendOnboardingEmail();
            }
        });
        static::updating(function ($model) {
            $model->updated_by = auth()->id();
            if ($model->isDirty('status') && $model->status === UserStatus::APPROVED) {
                $model->approved_at = now();
                $model->approved_by = auth()->id();
            }
        });
        static::updated(function ($model) {
            if ($model->wasChanged('status') && $model->status === UserStatus::APPROVED) {
                $model->sendOnboardingEmail();
            }
        });
        static::deleting(function ($model) {
            $model->deleted_by = auth()->id();
            $model->saveQuietly();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_id',
                'client_id',
                'client_app_user_id',
                'status',
                'approved_at',
                'approved_by',
                'is_blocked',
            ])
            ->logOnlyDirty();
    }

    public function sendOnboardingEmail(): void
    {
        $user = $this->user;
        if ($user) {
            $token = Password::createToken($user);
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            Mail::to($user->email)->queue(
                new OnboardingMail($user, $url)
            );
        }
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
