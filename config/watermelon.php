<?php

use NathanHeffley\LaravelWatermelon\WatermelonService;

return [

    'identifier' => env('WATERMELON_IDENTIFIER', 'watermelon_id'),

    'route' => env('WATERMELON_ROUTE', '/sync'),

    'middleware' => [],

    'debug_push' => env('WATERMELON_DEBUG_PUSH', false),

    'debug_pull' => env('WATERMELON_DEBUG_PULL', false),

    'resolveStartDateSync' => WatermelonService::class,

    'resolveMaxDateSync' => WatermelonService::class,

    'models' => [
        // 'tasks' => '\App\Models\Task',
    ],

    'migrations' => [
        //[
        //    'toVersion' => 2,
        //    'tables' => [
        //        'another_table',
        //    ]
        //]
    ],
];
