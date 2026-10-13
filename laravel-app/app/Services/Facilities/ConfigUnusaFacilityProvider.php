<?php

namespace App\Services\Facilities;

use App\Contracts\UnusaFacilityProvider;

class ConfigUnusaFacilityProvider implements UnusaFacilityProvider
{
    public function all(): array
    {
        return array_values(config('unusa.facilities', []));
    }
}
