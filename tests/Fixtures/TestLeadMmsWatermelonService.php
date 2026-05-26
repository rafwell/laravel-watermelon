<?php

namespace NathanHeffley\LaravelWatermelon\Tests\Fixtures;

use Carbon\Carbon;
use Illuminate\Http\Request;
use NathanHeffley\LaravelWatermelon\WatermelonServiceContract;

/** Simulates tenant-based first sync windows (fixtures for AlwaysPullFullTest). */
class TestLeadMmsWatermelonService implements WatermelonServiceContract
{
    public function resolveStartDateSync(Request $request): Carbon
    {
        return Carbon::parse('2025-06-01 12:00:00');
    }

    public function resolveMaxDateSync(Request $request, Carbon $lastPulledAt): Carbon
    {
        return $lastPulledAt->copy()->addMonths(3);
    }
}
