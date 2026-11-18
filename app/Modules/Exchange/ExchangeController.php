<?php
declare(strict_types=1);

namespace SwapFix\Modules\Exchange;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;
use SwapFix\Support\Labels;

final class ExchangeController extends Controller
{
    private const BLANK = ['title' => '', 'description' => '', 'category' => 'cellphone', 'brand' => '', 'model' => '', 'condition' => 'good', 'known_defects' => '', 'location' => '', 'photo_paths' => [], 'wanted_item' => '', 'wanted_details' => '', 'cash_preference' => 'none'];

    public function index(Request $request, Response $response): Response
    {
        $query = Input::query($request);
        $filters = [
            'q' => $query->text('q', 100), 'category' => $query->choice('category', array_keys(Labels::CATEGORIES)),
            'brand' => $query->text('brand', 60), 'condition' => $query->choice('condition', array_keys(Labels::CONDITIONS)),
            'location' => $query->text('location', 120),
        ];
        $page = $query->int('page', 1, 1, 400);
        $results = $this->app->exchanges()->search($filters, $page, $this->session($request)?->accessToken);
        return $this->page($request, $response, 'exchange/index', ['filters' => $filters, 'results' => $results, 'pageNumber' => $page, 'pageSize' => ExchangeRepository::PAGE_SIZE]);
    }

    public function show(Request $request, Response $response, array $args, array $extra = [], int $status = 200): Response
    {
        $user = $this->session($request);
        $id = $this->id($request, $args);
        $exchange = $this->found($request, $this->app->exchanges()->find($id, $user?->accessToken));
        return $this->page($request, $response, 'exchange/show', $extra + [
            'exchange' => $exchange,
            'isOwner' => $user !== null && $user->id === $exchange['owner_id'],
            'offers' => $user ? $this->app->exchanges()->offersFor($user->accessToken, $id) : [],
            'old' => OfferForm::BLANK,
        ], $status);
    }

    public function create(Request $request, Response $response): Response
    {
        $location = (string) ($this->user($request)->profile['location'] ?? '');
        return $this->page($request, $response, 'exchange/form', ['old' => ['location' => $location] + self::BLANK, 'exchange' => null]);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $exchange = $this->owned($request, $args);
        return $this->page($request, $response, 'exchange/form', ['old' => $exchange, 'exchange' => $exchange]);
    }

    public function store(Request $request, Response $response): Response
    {
        return $this->persist($request, $response, null);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->persist($request, $response, $this->owned($request, $args));
    }

    public function remove(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        return $this->act($request, $response, fn () => $this->app->exchanges()->remove($user->accessToken, $id), 'Exchange listing removed.', '/activity?tab=exchanges', '/exchange/' . $id);
    }

    public function complete(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        return $this->act($request, $response, fn (Input $input) => $this->app->exchanges()->complete($user->accessToken, $id, $input->uuid('expected_offer_id')), 'Exchange marked completed.', '/exchange/' . $id);
    }

    public function cancelAgreement(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        return $this->act($request, $response, fn (Input $input) => $this->app->exchanges()->cancelAgreement(
            $user->accessToken, $id, $input->text('note', 1000), $input->uuid('expected_offer_id')
        ), 'Agreement cancelled.', '/exchange/' . $id, '/exchange/' . $id . '?cancel=1');
    }

    public function submitOffer(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $input = Input::body($request);
        $uploads = $this->app->uploads($user);
        $terms = OfferForm::terms($input);
        try {
            OfferForm::requireCash($input);
            $photos = $uploads->take($request, 'photos', 'offer-images', 6);
            $result = $this->app->exchanges()->submitOffer($user->accessToken, $id, $terms, $photos);
        } catch (DomainError $error) {
            $uploads->discard();
            return $this->show($request, $response, $args, ['old' => $terms, 'offerError' => $error->getMessage()], 422);
        } catch (\Throwable $exception) {
            $uploads->discard();
            throw $exception;
        }
        $this->flash($request)->success('Trade offer sent.');
        return $this->redirect($response, '/offers/' . $result['id']);
    }

    private function owned(Request $request, array $args): array
    {
        $user = $this->user($request);
        $exchange = $this->found($request, $this->app->exchanges()->find($this->id($request, $args), $user->accessToken));
        if ($exchange['owner_id'] !== $user->id) {
            throw new HttpNotFoundException($request);
        }
        return $exchange;
    }

    private function persist(Request $request, Response $response, ?array $exchange): Response
    {
        $user = $this->user($request);
        $input = Input::body($request);
        $uploads = $this->app->uploads($user);
        $fields = [];
        foreach (['title' => 150, 'description' => 4000, 'category' => 20, 'brand' => 60, 'model' => 80, 'condition' => 20, 'known_defects' => 2000, 'location' => 120, 'wanted_item' => 200, 'wanted_details' => 2000, 'cash_preference' => 20] as $key => $max) {
            $fields[$key] = $input->text($key, $max);
        }
        $kept = array_values(array_intersect($exchange['photo_paths'] ?? [], $input->strings('keep_photos')));
        try {
            $paths = array_merge($kept, $uploads->take($request, 'photos', 'listing-images', max(0, 8 - count($kept))));
            $result = $this->app->exchanges()->save($user->accessToken, $exchange['id'] ?? null, $fields, $paths);
        } catch (DomainError $error) {
            $uploads->discard();
            return $this->page($request, $response, 'exchange/form', ['old' => ['photo_paths' => $kept] + $fields, 'exchange' => $exchange, 'error' => $error->getMessage()], 422);
        } catch (\Throwable $exception) {
            $uploads->discard();
            throw $exception;
        }
        $uploads->remove('listing-images', $result['removed_paths'] ?? []);
        $this->flash($request)->success($exchange ? 'Exchange listing updated.' : 'Exchange listing published.');
        return $this->redirect($response, '/exchange/' . $result['id']);
    }
}
