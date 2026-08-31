<?php

namespace App\Models;

use ElectricTomCat\GoogleAdsConversions\Models\Lead as PackageLead;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * The application's lead model.
 *
 * Everything about conversion storage, pruning and the HasConversions contract
 * comes from the package. This subclass exists so the app owns the class name
 * (and can add app-only relations later) without re-implementing the engine —
 * the previous copy had drifted and no longer held the retention fix.
 */
class Lead extends PackageLead
{
    use HasFactory;
}
