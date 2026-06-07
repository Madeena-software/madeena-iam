<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sessions';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ];

    /**
     * Get the user that owns the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parsed device details from the user agent.
     *
     * @return array{browser: string, operating_system: string, device: string, description: string}
     */
    public function getDeviceDetailsAttribute(): array
    {
        $userAgent = $this->user_agent ?? '';

        $browser = 'Unknown Browser';
        $os = 'Unknown OS';
        $device = 'Desktop';

        // Parse OS
        if (preg_match('/iphone/i', $userAgent)) {
            $os = 'iPhone';
            $device = 'Mobile';
        } elseif (preg_match('/ipad/i', $userAgent)) {
            $os = 'iPad';
            $device = 'Tablet';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'Android';
            $device = 'Mobile';
        } elseif (preg_match('/windows|win32/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        }

        // Parse Browser
        if (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/edge|edg/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'Firefox';
        }

        return [
            'browser' => $browser,
            'operating_system' => $os,
            'device' => $device,
            'description' => "{$browser} on {$os}",
        ];
    }
}
