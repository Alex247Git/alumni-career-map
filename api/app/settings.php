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
                    'host' => '127.0.0.1',
                    'port' => 3306,
                    'dbname' => 'alumni_ds_db',
                    'user' => 'alumni_api',
                    'pass' => 'ApiPass_2026!',
                    'charset' => 'utf8mb4',
                ],
                // JWT settings
                'jwt' => [
                    'secret' => 'AlumniDS_SecretKey_2026_AdvancedWebApps',
                    'issuer' => 'alumni.ds.uth.gr',
                    'expire' => 3600, // 1 hour
                ],
            ]);
        }
    ]);
};