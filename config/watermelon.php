<?php

return [

    'identifier' => env('WATERMELON_IDENTIFIER', 'watermelon_id'),

    'route' => env('WATERMELON_ROUTE', '/sync'),

    'middleware' => [],

    'debug_push' => env('WATERMELON_DEBUG_PUSH', false),

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
