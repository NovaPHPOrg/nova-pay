<?php

declare(strict_types=1);

return [
    'config' => [
        'framework_start' => [
            'nova\\plugin\\pay\\PayPluginManager',
        ],
    ],
    'require' => [
        'login',
        'tpl',
        'cookie',
        'http',
    ],
];
