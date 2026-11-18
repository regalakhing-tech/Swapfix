<?php
declare(strict_types=1);

namespace SwapFix\Modules\Exchange;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;

final class OfferController extends Controller
{
    public function show(Request $request, Response $response, array $args, array $extra = [], int $status = 200): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $offer = $this->found($request, $this->app->exchanges()->offer($user->accessToken, $id));
        $paths = array_merge(...array_map(static fn (array $terms): array => $terms['photo_paths'], $offer['terms'] ?: [['photo_paths' => []]]));
        $conversation = $this->app->exchanges()->conversationForOffer($user->accessToken, $id);
        $request = $this->markConversationRead($request, $conversation);
        return $this->page($request, $response, 'exchange/offer', $extra + [
            'offer' => $offer,
            'exchange' => $offer['exchange'],
            'actions' => OfferActions::for($offer, $offer['exchange'], $user->id),
            'photoUrls' => $this->app->storage()->signedUrls($user->accessToken, 'offer-images', $paths),
            'conversationId' => $conversation['id'] ?? null,
            'messages' => $conversation ? $this->app->messages()->recent($user->accessToken, $conversation['id']) : [],
            'old' => $offer['latest'] ?? OfferForm::BLANK,
        ], $status);
    }

    public function accept(Request $request, Response $response, array $args): Response
    {
        return $this->decide($request, $response, $args, 'Offer accepted. Arrange the handover in chat.', fn (string $token, string $id, int $version) => $this->app->exchanges()->accept($token, $id, $version));
    }

    public function decline(Request $request, Response $response, array $args): Response
    {
        $note = Input::body($request)->text('note', 1000);
        return $this->decide($request, $response, $args, 'Offer declined.', fn (string $token, string $id, int $version) => $this->app->exchanges()->decline($token, $id, $version, $note));
    }

    public function withdraw(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        return $this->act($request, $response, fn () => $this->app->exchanges()->withdraw($user->accessToken, $id), 'Offer withdrawn.', '/offers/' . $id);
    }

    public function counter(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $input = Input::body($request);
        $uploads = $this->app->uploads($user);
        $terms = OfferForm::terms($input);
        try {
            OfferForm::requireCash($input);
            $photos = $uploads->take($request, 'photos', 'offer-images', 6);
            $this->app->exchanges()->counter($user->accessToken, $id, $input->int('version', 0, 0, 100000), $terms, $photos === [] ? null : $photos);
        } catch (DomainError $error) {
            $uploads->discard();
            return $this->show($request, $response, $args, ['old' => $terms, 'counterError' => $error->getMessage()], $error->status === 409 ? 409 : 422);
        } catch (\Throwable $exception) {
            $uploads->discard();
            throw $exception;
        }
        $this->flash($request)->success('Counteroffer sent.');
        return $this->redirect($response, '/offers/' . $id);
    }

    private function decide(Request $request, Response $response, array $args, string $success, callable $decision): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        return $this->act($request, $response, static fn (Input $input) => $decision($user->accessToken, $id, $input->int('version', 0, 0, 100000)), $success, '/offers/' . $id);
    }
}
