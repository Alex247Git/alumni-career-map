<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            return new Settings([
                'displayErrorDetails' => true,
                'logError'            => false,
                'logErrorDetails'     => false,
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Logger::DEBUG,
                ],
                // Database settings
                'db' => [
                    'host' => getenv('DB_HOST') ?: '127.0.0.1',
                    'port' => getenv('DB_PORT') ?: 3306,
                    'dbname' => getenv('DB_NAME') ?: 'alumni_ds_db',
                    'user' => getenv('DB_USER') ?: 'alumni_api',
                    'pass' => getenv('DB_PASS') ?: 'change-me',
                    'charset' => 'utf8mb4',
                ],
                // JWT settings
                'jwt' => [
                    // !! Provide strong values via env (see .env.example) in production !!
                    'secret' => getenv('JWT_SECRET') ?: 'dev-only-insecure-secret-change-me',
                    'issuer' => getenv('JWT_ISSUER') ?: 'alumni-career-map',
                    'expire' => 3600, // 1 hour
                ],
            ]);
        }
    ]);
};