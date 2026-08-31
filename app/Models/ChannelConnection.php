<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $license_id
 * @property string $channel
 * @property array<string, mixed>|null $credentials
 * @property string|null $account_id
 * @property string|null $account_name
 * @property string $status
 */
class ChannelConnection extends Model
{
    use HasFactory;

    /** Channels a customer can connect through omnisignal.dev. */
    public const HOSTED_CHANNELS = ['google'];

    protected $fillable = [
        'license_id',
        'channel',
        'credentials',
        'account_id',
        'account_name',
        'status',
        'last_error',
        'verified_at',
    ];

    protected $casts = [
        // Live credentials for someone else's ad account. Encrypted at rest,
        // and never exposed by the API or rendered in the portal.
        'credentials' => 'encrypted:array',
        'verified_at' => 'datetime',
    ];

    /**
     * Keep credentials out of anything that serialises the model by accident.
     *
     * @var array<int, string>
     */
    protected $hidden = ['credentials'];

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function isUsable(): bool
    {
        return $this->status === 'connected' && ! empty($this->credentials);
    }

    /**
     * Read one credential without exposing the whole set.
     */
    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function markNeedsReauth(string $reason): void
    {
        $this->forceFill([
            'status' => 'needs_reauth',
            'last_error' => mb_substr($reason, 0, 500),
        ])->save();
    }

    public function markVerified(): void
    {
        $this->forceFill([
            'status' => 'connected',
            'last_error' => null,
            'verified_at' => now(),
        ])->save();
    }
}
