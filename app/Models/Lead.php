<?php

namespace App\Models;

use App\OmniSignal\Contracts\HasConversions;
use App\OmniSignal\Models\Concerns\HasConversionsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class Lead extends Model implements HasConversions
{
    use HasConversionsTrait;
    use HasFactory;
    use Prunable;

    protected $fillable = [
        'gclid',
        'gbraid',
        'wbraid',
        'visitor_id',
        'conversions',
        'landing_page',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gad_source',
        'gad_campaignid',
    ];

    protected $casts = [
        'conversions' => AsCollection::class,
    ];

    /**
     * Get the prunable model query for GDPR / retention compliance.
     */
    public function prunable(): Builder
    {
        $retentionDays = (int) config('omnisignal.privacy.retention_days', 90);

        return static::where('updated_at', '<=', now()->subDays($retentionDays));
    }
}
