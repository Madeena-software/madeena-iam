<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
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
        });
        static::updating(function ($model) {
            $model->updated_by = Auth::id();
        });
        static::deleting(function ($model) {
            $model->deleted_by = Auth::id();
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
