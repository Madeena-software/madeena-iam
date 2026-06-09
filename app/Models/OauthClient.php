<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Laravel\Passport\Client as PassportClient;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class OauthClient extends PassportClient
{
    use LogsActivity, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'grant_types' => 'array',
        'scopes' => 'array',
        'redirect_uris' => 'array',
        'personal_access_client' => 'bool',
        'password_client' => 'bool',
        'revoked' => 'bool',
    ];

    /**
     * Interact with the client's redirect URIs.
     */
    protected function redirectUris(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes): array {
                if (empty($value)) {
                    if (! empty($attributes['redirect'])) {
                        return explode(',', $attributes['redirect']);
                    }

                    return [];
                }

                $decoded = $this->fromJson($value);

                if (is_array($decoded)) {
                    return $decoded;
                }

                if (is_string($decoded)) {
                    return array_map('trim', explode(',', $decoded));
                }

                return [];
            },
        );
    }

    /**
     * Interact with the client's grant types.
     */
    protected function grantTypes(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                if (! isset($value)) {
                    return array_keys(array_filter([
                        'authorization_code' => ! empty($this->redirect_uris),
                        'client_credentials' => $this->confidential() && $this->firstParty(),
                        'implicit' => ! empty($this->redirect_uris),
                        'password' => $this->password_client,
                        'personal_access' => $this->personal_access_client && $this->confidential(),
                        'refresh_token' => true,
                        'urn:ietf:params:oauth:grant-type:device_code' => true,
                    ]));
                }

                $decoded = $this->fromJson($value);

                if (is_array($decoded)) {
                    return $decoded;
                }

                if (is_string($decoded)) {
                    return array_map('trim', explode(',', $decoded));
                }

                return [];
            },
        );
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_by = Auth::id();
            if (empty($model->provider)) {
                $model->provider = 'users';
            }
            if (empty($model->owner_type)) {
                $model->owner_type = 'Company';
            }
            if (empty($model->owner_id)) {
                $defaultOwner = Owner::firstOrCreate(
                    ['name' => 'PT Madeena Karya Indonesia', 'type' => 'Company']
                );
                $model->owner_id = $defaultOwner->id;
            }
        });
        static::updating(function ($model) {
            $model->updated_by = Auth::id();
        });
        static::deleting(function ($model) {
            $model->deleted_by = Auth::id();
            $model->saveQuietly();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'redirect_uris',
                'grant_types',
                'revoked',
                'is_active',
                'description',
                'app_logo_path',
                'owner_type',
                'owner_id',
            ])
            ->logOnlyDirty();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'client_user', 'client_id', 'user_id')
            ->using(ClientUser::class)
            ->withPivot(['status', 'approved_at', 'approved_by', 'is_blocked', 'client_app_user_id'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    protected function secret(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: function (?string $value) {
                $this->plainSecret = $value;

                if (empty($value)) {
                    return null;
                }

                return Crypt::encryptString($value);
            }
        );
    }
}
