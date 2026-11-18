<?php
declare(strict_types=1);

use Slim\App;
use SwapFix\Controllers\HealthController;

return static function (App $app, array $services): void {
    $app->get('/api/health', new HealthController($services));
};
