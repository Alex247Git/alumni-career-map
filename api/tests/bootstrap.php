<?php

require __DIR__ . '/../vendor/autoload.php';

// Silence PHP 8.4+ deprecation notices emitted by dependencies (php-di etc.)
// so that phpunit runs clean on newer PHP versions too.
error_reporting(E_ALL & ~E_DEPRECATED);
