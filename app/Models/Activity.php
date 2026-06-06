<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    // Extended to allow Filament to easily find it and generate a resource.
}
