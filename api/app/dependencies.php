<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Controllers\AlumniController;
use App\Controllers\AuthController;
use App\Controllers\JobController;
use App\Middleware\JwtMiddleware;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        // Register controllers as services (inject container)
        AuthController::class => function (ContainerInterface $c) {
            return new AuthController($c);
        },

        AlumniController::class => function (ContainerInterface $c) {
            return new AlumniController($c);
        },

        JobController::class => function (ContainerInterface $c) {
            return new JobController($c);
        },

        // Register JWT Middleware
        JwtMiddleware::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);
            return new JwtMiddleware($settings->get('jwt')['secret']);
        },
    ]);
};