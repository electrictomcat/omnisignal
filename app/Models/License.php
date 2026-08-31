<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        $domain = strtolower(trim($domain));
        $instances = $this->instances ?? [];

        return in_array($domain, $instances, true);
    }

    public function activate(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        $instances = $this->instances ?? [];

        if (in_array($domain, $instances, true)) {
            return true;
        }

        if (count($instances) >= $this->activation_limit) {
            return false;
        }

        $instances[] = $domain;
        $this->instances = $instances;
        $this->activation_count = count($instances);
        $this->save();

        return true;
    }

    public function deactivate(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        $instances = $this->instances ?? [];

        if (! in_array($domain, $instances, true)) {
            return false;
        }

        $instances = array_values(array_filter($instances, fn ($d) => $d !== $domain));
        $this->instances = $instances;
        $this->activation_count = count($instances);
        $this->save();

        return true;
    }
}
