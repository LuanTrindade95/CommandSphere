<?php

return [
    'default_community_slug' => env('COMMANDSPHERE_DEFAULT_COMMUNITY_SLUG', 'celem-ecosystem'),

    'github' => [
        'client' => env('COMMANDSPHERE_GITHUB_CLIENT', 'http'),
        'fixture_path' => env('COMMANDSPHERE_GITHUB_FIXTURE_PATH'),
        'token' => env('GITHUB_TOKEN'),
    ],

    'analytics' => [
        'view_dedupe_minutes' => env('COMMANDSPHERE_VIEW_DEDUPE_MINUTES', 10),
    ],
];
