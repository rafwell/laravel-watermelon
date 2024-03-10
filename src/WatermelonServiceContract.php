<?php

namespace NathanHeffley\LaravelWatermelon;

use Carbon\Carbon;
use Illuminate\Http\Request;

interface WatermelonServiceContract
{
    public function resolveStartDateSync(Request $request): Carbon;

    public function resolveMaxDateSync(Request $request, Carbon $lastPulledAt): Carbon;
}
