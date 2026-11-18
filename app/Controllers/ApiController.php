<?php
declare(strict_types=1);

namespace SwapFix\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use SwapFix\Application\Container;
use SwapFix\Http\Input;
use SwapFix\Http\Json;
use SwapFix\Support\Labels;

final class ApiController
{
    public function __construct(private readonly Container $app) {}

    public function listings(Request $request, Response $response): Response
    {
        $query = Input::query($request);
        $filters = [
            'q' => $query->text('q', 100), 'category' => $query->choice('category', array_keys(Labels::CATEGORIES)),
            'brand' => $query->text('brand', 60), 'condition' => $query->choice('condition', array_keys(Labels::CONDITIONS)),
            'min' => $query->optionalMoney('min'), 'max' => $query->optionalMoney('max'), 'location' => $query->text('location', 120),
            'sort' => $query->choice('sort', ['newest', 'price_asc', 'price_desc'], 'newest'),
        ];
        return Json::respond($response, $this->app->listings()->search($filters, $query->int('page', 1, 1, 400), null));
    }

    public function me(Request $request, Response $response): Response
    {
        return Json::respond($response, $this->app->db()->rpc('sf_session', [], (string) $request->getAttribute('access_token')));
    }

    /** Scheduled worker. Disabled (404) until its secrets and SMTP are configured. */
    public function dispatchMail(Request $request, Response $response): Response
    {
        $dispatcher = $this->app->mailDispatcher();
        if ($dispatcher === null) {
            throw new HttpNotFoundException($request);
        }
        $expected = 'Bearer ' . $this->app->services['mail_worker']['cron_secret'];
        if (!hash_equals($expected, $request->getHeaderLine('Authorization'))) {
            return Json::respond($response, ['error' => 'Authentication required.'], 401);
        }
        return Json::respond($response, $dispatcher->dispatch());
    }
}
