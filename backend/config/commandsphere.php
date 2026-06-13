<?php

return [
    'default_community_slug' => env('COMMANDSPHERE_DEFAULT_COMMUNITY_SLUG', 'celem-ecosystem'),

    'github' => [
        'client' => env('COMMANDSPHERE_GITHUB_CLIENT', 'http'),
        'fixture_path' => env('COMMANDSPHERE_GITHUB_FIXTURE_PATH'),
        'token' => env('GITHUB_TOKEN'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'sync' => [
        'schedule' => env('COMMANDSPHERE_SYNC_SCHEDULE', '*/30 * * * *'),
    ],

    'analytics' => [
        'view_dedupe_minutes' => env('COMMANDSPHERE_VIEW_DEDUPE_MINUTES', 10),
        'max_period_days' => env('COMMANDSPHERE_ANALYTICS_MAX_DAYS', 365),
    ],
];
