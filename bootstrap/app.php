<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use SwapFix\Http\Json;
use SwapFix\Http\SecurityHeaders;

require_once dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/app.php';
$services = require dirname(__DIR__) . '/config/services.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
(require dirname(__DIR__) . '/routes/web.php')($app);
(require dirname(__DIR__) . '/routes/api.php')($app, $services);
$errors = $app->addErrorMiddleware(false, false, false);
$errors->setDefaultErrorHandler(static function (ServerRequestInterface $request, Throwable $exception) use ($app) {
    $status = $exception instanceof HttpException ? $exception->getCode() : 500;
    $messages = [400 => 'Invalid request.', 401 => 'Authentication required.', 403 => 'Access denied.', 404 => 'Not found.', 405 => 'Method not allowed.', 413 => 'Upload too large.', 422 => 'Invalid input.', 503 => 'Service unavailable.'];
    if ($status >= 500) {
        error_log('SwapFix request failed: ' . $exception::class);
    }
    $response = Json::respond($app->getResponseFactory()->createResponse(), ['error' => $messages[$status] ?? 'An unexpected error occurred.'], $status);
    if ($status === 405 && $exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
        $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
    }
    return $response;
});
$app->add(new SecurityHeaders());
return $app;
