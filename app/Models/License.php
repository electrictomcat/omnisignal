<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'product_id',
        'variant_id',
        'tier',
        'license_key',
        'status',
        'activation_limit',
        'activation_count',
        'instances',
        'expires_at',
    ];

    protected $casts = [
        'instances' => 'array',
        'expires_at' => 'datetime',
        'activation_limit' => 'integer',
        'activation_count' => 'integer',
    ];

    /**
     * Ad-platform accounts connected to this licence.
     *
     * @return HasMany<ChannelConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(ChannelConnection::class);
    }

    /**
     * Channels we upload for on this customer's behalf.
     *
     * @return array<int, string>
     */
    public function hostedChannels(): array
    {
        return $this->connections
            ->filter(fn (ChannelConnection $connection) => $connection->isUsable())
            ->pluck('channel')
            ->values()
            ->all();
    }

    public static function generateKey(): string
    {
        return 'OMNI-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isActivatedFor(string $domain): bool
    {
        return in_array($this->normalizeDomain($domain), $this->instances ?? [], true);
    }

    /**
     * Activate a domain against this licence.
     *
     * Runs inside a transaction with the row locked: read, limit check and
     * write have to be one step, or two simultaneous activations both pass the
     * check and a one-domain licence ends up holding two.
     */
    public function activate(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);

        if ($domain === '') {
            return false;
        }

        return DB::transaction(function () use ($domain) {
            /** @var self $license */
            $license = self::query()->lockForUpdate()->findOrFail($this->getKey());

            $instances = $license->instances ?? [];

            if (in_array($domain, $instances, true)) {
                $this->syncFrom($license);

                return true;
            }

            if (count($instances) >= $license->activation_limit) {
                $this->syncFrom($license);

                return false;
            }

            $instances[] = $domain;
            $license->instances = $instances;
            $license->activation_count = count($instances);
            $license->save();

            $this->syncFrom($license);

            return true;
        });
    }

    public function deactivate(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);

        if ($domain === '') {
            return false;
        }

        return DB::transaction(function () use ($domain) {
            /** @var self $license */
            $license = self::query()->lockForUpdate()->findOrFail($this->getKey());

            $instances = $license->instances ?? [];

            if (! in_array($domain, $instances, true)) {
                $this->syncFrom($license);

                return false;
            }

            $license->instances = array_values(array_filter($instances, fn ($d) => $d !== $domain));
            $license->activation_count = count($license->instances);
            $license->save();

            $this->syncFrom($license);

            return true;
        });
    }

    /**
     * Normalise a domain to the form activations are stored in.
     *
     * Accepts what people actually paste — a full URL, a www. prefix, a
     * trailing slash, mixed case — so the same site cannot consume two slots.
     */
    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return '';
        }

        if (str_contains($domain, '://')) {
            $domain = (string) (parse_url($domain, PHP_URL_HOST) ?: $domain);
        }

        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        return preg_replace('/^www\./', '', $domain) ?? $domain;
    }

    /**
     * Copy the locked row's activation state back onto this instance so the
     * caller sees current counts without a second query.
     */
    protected function syncFrom(self $fresh): void
    {
        $this->instances = $fresh->instances;
        $this->activation_count = $fresh->activation_count;
        $this->syncOriginal();
    }
}
