<?php
declare(strict_types=1);

namespace SwapFix\Modules\Repair;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;

/** Public technician discovery, plus each technician's own profile, services and job board. */
final class TechnicianController extends Controller
{
    public function index(Request $request, Response $response): Response
    {
        $query = Input::query($request);
        $filters = ['q' => $query->text('q', 100), 'area' => $query->text('area', 120), 'specialty' => $query->text('specialty', 60), 'brand' => $query->text('brand', 60)];
        $page = $query->int('page', 1, 1, 400);
        $results = $this->app->technicians()->search($filters, $page, $this->session($request)?->accessToken);
        return $this->page($request, $response, 'repair/index', ['filters' => $filters, 'results' => $results, 'pageNumber' => $page, 'pageSize' => TechnicianRepository::PAGE_SIZE]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $this->id($request, $args);
        $token = $this->session($request)?->accessToken;
        $technicians = $this->app->technicians();
        $technician = $this->found($request, $technicians->find($id, $token));
        if (($technician['profile']['account_type'] ?? '') !== 'technician') {
            throw new HttpNotFoundException($request);
        }
        return $this->page($request, $response, 'repair/technician', [
            'technician' => $technician,
            'services' => $technicians->activeServices($id, $token),
            'reviews' => $technicians->reviews($id, $token),
            'isSelf' => $this->session($request)?->id === $id,
        ]);
    }

    public function editProfile(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        return $this->page($request, $response, 'repair/profile-form', ['old' => $this->app->technicians()->find($user->id, $user->accessToken) ?? []]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $input = Input::body($request);
        $fields = [
            'headline' => $input->text('headline', 120), 'bio' => $input->text('bio', 2000), 'location' => $input->text('location', 120),
            'service_area' => $input->text('service_area', 200), 'available_hours' => $input->text('available_hours', 200),
            'specialties' => $input->list('specialties'), 'supported_brands' => $input->list('supported_brands'), 'supported_models' => $input->list('supported_models'),
        ];
        try {
            $this->app->technicians()->saveProfile($user->accessToken, $fields);
        } catch (DomainError $error) {
            return $this->page($request, $response, 'repair/profile-form', ['old' => $fields, 'error' => $error->getMessage()], 422);
        }
        $this->flash($request)->success('Public profile saved.');
        return $this->redirect($response, '/technician/profile');
    }

    public function services(Request $request, Response $response, array $args = [], array $extra = [], int $status = 200): Response
    {
        $user = $this->user($request);
        return $this->page($request, $response, 'repair/services', $extra + ['services' => $this->app->technicians()->ownServices($user->accessToken, $user->id)], $status);
    }

    public function saveService(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = isset($args['id']) ? $this->id($request, $args) : null;
        $input = Input::body($request);
        $fields = [
            'name' => $input->text('name', 120), 'description' => $input->text('description', 2000),
            'supported_brands' => $input->list('supported_brands'), 'supported_models' => $input->list('supported_models'),
            'estimated_price' => $input->text('estimated_price', 20),
        ];
        try {
            $fields['estimated_price'] = $input->money('estimated_price', 'Estimated price');
            $this->app->technicians()->saveService($user->accessToken, $id, $fields);
        } catch (DomainError $error) {
            return $this->services($request, $response, [], ['formError' => $error->getMessage(), 'old' => ['id' => $id] + $fields], 422);
        }
        $this->flash($request)->success($id ? 'Service updated.' : 'Service published.');
        return $this->redirect($response, '/technician/services');
    }

    public function setServiceActive(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $active = Input::body($request)->flag('active');
        return $this->act($request, $response, fn () => $this->app->technicians()->setServiceActive($user->accessToken, $id, $active), $active ? 'Service published.' : 'Service hidden.', '/technician/services');
    }

    public function dashboard(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $board = RepairRules::board($this->app->repairs()->forTechnician($user->accessToken, $user->id));
        return $this->page($request, $response, 'repair/dashboard', ['board' => $board]);
    }
}
