<?php
declare(strict_types=1);

namespace SwapFix\Modules\Repair;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;
use SwapFix\Support\Labels;

final class RepairController extends Controller
{
    private const BLANK = ['device_type' => 'cellphone', 'device_brand' => '', 'device_model' => '', 'problem' => '', 'service_id' => ''];

    public function index(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        return $this->page($request, $response, 'repair/list', ['repairs' => $this->app->repairs()->forCustomer($user->accessToken, $user->id)]);
    }

    public function create(Request $request, Response $response, array $args, array $extra = [], int $status = 200): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $technician = $this->found($request, $this->app->technicians()->find($id, $user->accessToken));
        if ($id === $user->id) {
            $this->flash($request)->error('You cannot request a repair from yourself.');
            return $this->redirect($response, '/technicians/' . $id);
        }
        $old = ['service_id' => Input::query($request)->uuid('service') ?? ''] + self::BLANK;
        return $this->page($request, $response, 'repair/request-form', $extra + [
            'technician' => $technician, 'services' => $this->app->technicians()->activeServices($id, $user->accessToken), 'old' => $old,
        ], $status);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $technicianId = $this->id($request, $args);
        $input = Input::body($request);
        $uploads = $this->app->uploads($user);
        $fields = [
            'device_type' => $input->text('device_type', 20), 'device_brand' => $input->text('device_brand', 60),
            'device_model' => $input->text('device_model', 80), 'problem' => $input->text('problem', 4000),
            'service_id' => $input->uuid('service_id') ?? '',
        ];
        try {
            $photos = $uploads->take($request, 'photos', 'repair-images', 6);
            $result = $this->app->repairs()->create($user->accessToken, $technicianId, $fields['service_id'] ?: null, $fields, $photos);
        } catch (DomainError $error) {
            $uploads->discard();
            return $this->create($request, $response, $args, ['old' => $fields, 'error' => $error->getMessage()], 422);
        } catch (\Throwable $exception) {
            $uploads->discard();
            throw $exception;
        }
        $this->flash($request)->success('Repair request sent.');
        return $this->redirect($response, '/repairs/' . $result['id']);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $repair = $this->found($request, $this->app->repairs()->find($user->accessToken, $this->id($request, $args)));
        $conversationId = $repair['conversation']['id'] ?? null;
        return $this->page($request, $response, 'repair/show', [
            'repair' => $repair,
            'actions' => RepairRules::actions($repair, $user->id),
            'photoUrls' => $this->app->storage()->signedUrls($user->accessToken, 'repair-images', $repair['photo_paths']),
            'conversationId' => $conversationId,
            'messages' => $conversationId ? $this->app->messages()->recent($user->accessToken, $conversationId) : [],
        ]);
    }

    public function quote(Request $request, Response $response, array $args): Response
    {
        return $this->change($request, $response, $args, 'Quotation sent.', 'quote', fn (string $token, string $id, Input $in) => $this->app->repairs()->sendQuotation(
            $token, $id, $in->text('scope'), $in->text('parts', 2000), (string) $in->money('cost', 'Repair cost'), (string) $in->date('estimated_completion', 'Completion date')
        ));
    }

    public function decideQuotation(Request $request, Response $response, array $args): Response
    {
        $quotationId = $this->id($request, $args, 'quotation');
        $decision = Input::body($request)->choice('decision', ['accepted', 'declined']);
        return $this->change($request, $response, $args, $decision === 'accepted' ? 'Quotation accepted.' : 'Quotation declined.', null, fn (string $token, string $id, Input $in) => $this->app->repairs()->decideQuotation($token, $quotationId, $decision, $in->text('note', 1000)));
    }

    public function requestAddedCost(Request $request, Response $response, array $args): Response
    {
        return $this->change($request, $response, $args, 'Added cost sent for approval.', 'added', fn (string $token, string $id, Input $in) => $this->app->repairs()->requestAddedCost(
            $token, $id, $in->text('reason', 2000), $in->text('proposed_work', 2000), (string) $in->money('amount', 'Added amount'), $in->date('revised_completion', 'Revised completion date', false)
        ));
    }

    public function withdrawAddedCost(Request $request, Response $response, array $args): Response
    {
        $costId = $this->id($request, $args, 'cost');
        return $this->change($request, $response, $args, 'Added cost withdrawn.', null, fn (string $token) => $this->app->repairs()->withdrawAddedCost($token, $costId));
    }

    public function decideAddedCost(Request $request, Response $response, array $args): Response
    {
        $costId = $this->id($request, $args, 'cost');
        $decision = Input::body($request)->choice('decision', ['accepted', 'declined']);
        return $this->change($request, $response, $args, $decision === 'accepted' ? 'Added cost approved.' : 'Added cost declined.', null, fn (string $token, string $id, Input $in) => $this->app->repairs()->decideAddedCost($token, $costId, $decision, $in->text('note', 1000)));
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $status = Input::body($request)->choice('status', Labels::REPAIR_FLOW);
        return $this->change($request, $response, $args, 'Repair updated: ' . Labels::status($status) . '.', null, fn (string $token, string $id, Input $in) => $this->app->repairs()->updateStatus($token, $id, $in->text('expected_status', 30), $status, $in->text('note', 1000)));
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        return $this->change($request, $response, $args, 'Repair cancelled.', 'cancel', fn (string $token, string $id, Input $in) => $this->app->repairs()->cancel($token, $id, $in->text('reason', 1000)));
    }

    public function review(Request $request, Response $response, array $args): Response
    {
        return $this->change($request, $response, $args, 'Thanks for your review.', 'review', fn (string $token, string $id, Input $in) => $this->app->repairs()->review($token, $id, $in->int('rating', 0, 0, 5), $in->text('comment', 2000)));
    }

    private function change(Request $request, Response $response, array $args, string $success, ?string $dialog, callable $operation): Response
    {
        $token = $this->user($request)->accessToken;
        $id = $this->id($request, $args);
        $url = '/repairs/' . $id;
        return $this->act($request, $response, static fn (Input $input) => $operation($token, $id, $input), $success, $url, $dialog ? $url . '?' . $dialog . '=1' : $url);
    }
}
