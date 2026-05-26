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

    /*
     * Lookup / reference tables: always sent in full on pull (ignore created_at windows).
     * Use for tiny global enums (status, types) referenced by transactional tables.
     */
    'always_pull_full' => [
        // Example: 'status_ordens_servicos',
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
