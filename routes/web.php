<?php
declare(strict_types=1);

use Slim\App;
use SwapFix\Controllers\HomeController;

return static function (App $app): void {
    $app->get('/', new HomeController());
};
