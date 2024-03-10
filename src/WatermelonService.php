<?php

namespace NathanHeffley\LaravelWatermelon;

use Carbon\Carbon;
use Illuminate\Http\Request;

class WatermelonService implements WatermelonServiceContract
{
    public function resolveStartDateSync(Request $request): Carbon
    {
        throw new \Exception('You must implement resolveStartDateSync method in your WatermelonService class.');
    }

    public function resolveMaxDateSync(Request $request, Carbon $lastPulledAt): Carbon
    {
        return $lastPulledAt->copy()->addMonth();
    }
}
