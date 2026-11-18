<?php
declare(strict_types=1);

namespace SwapFix\Modules\Marketplace;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;
use SwapFix\Support\Labels;

final class ListingController extends Controller
{
    private const BLANK = ['title' => '', 'description' => '', 'category' => 'cellphone', 'brand' => '', 'model' => '', 'condition' => 'good', 'known_defects' => '', 'price' => '', 'location' => '', 'photo_paths' => []];

    public function index(Request $request, Response $response): Response
    {
        $query = Input::query($request);
        $filters = [
            'q' => $query->text('q', 100), 'category' => $query->choice('category', array_keys(Labels::CATEGORIES)),
            'brand' => $query->text('brand', 60), 'condition' => $query->choice('condition', array_keys(Labels::CONDITIONS)),
            'min' => $query->optionalMoney('min'), 'max' => $query->optionalMoney('max'), 'location' => $query->text('location', 120),
            'sort' => $query->choice('sort', ['newest', 'price_asc', 'price_desc'], 'newest'),
        ];
        $page = $query->int('page', 1, 1, 400);
        $results = $this->app->listings()->search($filters, $page, $this->session($request)?->accessToken);
        return $this->page($request, $response, 'market/index', ['filters' => $filters, 'results' => $results, 'pageNumber' => $page, 'pageSize' => ListingRepository::PAGE_SIZE]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $this->session($request);
        $id = $this->id($request, $args);
        $listing = $this->found($request, $this->app->listings()->find($id, $user?->accessToken));
        $isOwner = $user !== null && $user->id === $listing['owner_id'];
        return $this->page($request, $response, 'market/show', [
            'listing' => $listing, 'isOwner' => $isOwner,
            'inquiries' => $isOwner ? $this->app->listings()->inquiries($user->accessToken, $id) : [],
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $location = (string) ($this->user($request)->profile['location'] ?? '');
        return $this->page($request, $response, 'market/form', ['old' => ['location' => $location] + self::BLANK, 'listing' => null]);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $listing = $this->owned($request, $args);
        return $this->page($request, $response, 'market/form', ['old' => $listing, 'listing' => $listing]);
    }

    public function store(Request $request, Response $response): Response
    {
        return $this->persist($request, $response, null);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->persist($request, $response, $this->owned($request, $args));
    }

    public function setStatus(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $input = Input::body($request);
        $status = $input->choice('status', ['available', 'reserved', 'sold', 'removed']);
        $target = $status === 'removed' ? '/activity' : '/market/' . $id;
        return $this->act($request, $response, fn () => $this->app->listings()->setStatus($user->accessToken, $id, $status, $input->text('expected_status', 20)), 'Listing marked ' . Labels::status($status) . '.', $target, '/market/' . $id);
    }

    public function inquire(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        try {
            $result = $this->app->listings()->inquire($user->accessToken, $id, Input::body($request)->text('message'));
        } catch (DomainError $error) {
            $this->flash($request)->error($error->getMessage());
            return $this->redirect($response, '/market/' . $id . '?inquire=1');
        }
        $this->flash($request)->success('Inquiry sent.');
        return $this->redirect($response, '/messages/' . $result['conversation_id']);
    }

    private function owned(Request $request, array $args): array
    {
        $user = $this->user($request);
        $listing = $this->found($request, $this->app->listings()->find($this->id($request, $args), $user->accessToken));
        if ($listing['owner_id'] !== $user->id) {
            throw new HttpNotFoundException($request);
        }
        return $listing;
    }

    private function persist(Request $request, Response $response, ?array $listing): Response
    {
        $user = $this->user($request);
        $input = Input::body($request);
        $uploads = $this->app->uploads($user);
        $fields = [];
        foreach (['title' => 150, 'description' => 4000, 'category' => 20, 'brand' => 60, 'model' => 80, 'condition' => 20, 'known_defects' => 2000, 'location' => 120] as $key => $max) {
            $fields[$key] = $input->text($key, $max);
        }
        $fields['price'] = $input->text('price', 20);
        $kept = array_values(array_intersect($listing['photo_paths'] ?? [], $input->strings('keep_photos')));
        try {
            $fields['price'] = $input->money('price', 'Price');
            $paths = array_merge($kept, $uploads->take($request, 'photos', 'listing-images', max(0, 8 - count($kept))));
            $result = $this->app->listings()->save($user->accessToken, $listing['id'] ?? null, $fields, $paths);
        } catch (DomainError $error) {
            $uploads->discard();
            return $this->page($request, $response, 'market/form', ['old' => ['photo_paths' => $kept] + $fields, 'listing' => $listing, 'error' => $error->getMessage()], 422);
        } catch (\Throwable $exception) {
            $uploads->discard();
            throw $exception;
        }
        $uploads->remove('listing-images', $result['removed_paths'] ?? []);
        $this->flash($request)->success($listing ? 'Listing updated.' : 'Listing published.');
        return $this->redirect($response, '/market/' . $result['id']);
    }
}
