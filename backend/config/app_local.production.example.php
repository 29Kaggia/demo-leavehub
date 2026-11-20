<?php
declare(strict_types=1);

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;

/* Copy this file to config/app_local.php and replace every placeholder. */
return [
    'debug' => false,
    'App' => [
        'fullBaseUrl' => 'https://leave.tech100.africa',
    ],
    'Security' => [
        'salt' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',
    ],
    'Datasources' => [
        'default' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'host' => 'localhost',
            'username' => 'REPLACE_WITH_DATABASE_USERNAME',
            'password' => 'REPLACE_WITH_DATABASE_PASSWORD',
            'database' => 'REPLACE_WITH_DATABASE_NAME',
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
            'cacheMetadata' => true,
            'log' => false,
        ],
    ],
    'Email' => [
        'default' => [
            'from' => ['no-reply@tech100.africa' => 'LeaveHub'],
        ],
    ],
    'Auth' => [
        'allowSignup' => false,
    ],
    'Deployment' => [
        // Use a unique random value for the one-time migration page, then remove it.
        'migrationToken' => 'REPLACE_WITH_A_UNIQUE_ONE_TIME_TOKEN',
    ],
];
