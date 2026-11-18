<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use SwapFix\Application\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/app.php';
$services = require dirname(__DIR__) . '/config/services.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');

return Application::create($config, $services);
