<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$configFile = $root . '/config/config.php';
if (!is_readable($configFile)) {
    $configFile = $root . '/config/config.example.php';
}
$config = require $configFile;

date_default_timezone_set($config['timezone']);

require_once $root . '/lib/Db.php';
require_once $root . '/lib/Auth.php';
require_once $root . '/lib/Api.php';
