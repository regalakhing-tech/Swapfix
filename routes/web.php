<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy as Group;
use SwapFix\Application\Container;
use SwapFix\Controllers\HomeController;
use SwapFix\Http\RouteGuards;
use SwapFix\Modules\Accounts\AccountController;
use SwapFix\Modules\Accounts\AuthController;
use SwapFix\Modules\Activity\ActivityController;
use SwapFix\Modules\Exchange\ExchangeController;
use SwapFix\Modules\Exchange\OfferController;
use SwapFix\Modules\Marketplace\ListingController;
use SwapFix\Modules\Messaging\MessageController;
use SwapFix\Modules\Notifications\NotificationController;
use SwapFix\Modules\Repair\RepairController;
use SwapFix\Modules\Repair\TechnicianController;

return static function (App $app, Container $container): void {
    $guards = new RouteGuards($container);
    $auth = new AuthController($container);
    $account = new AccountController($container);
    $listings = new ListingController($container);
    $exchanges = new ExchangeController($container);
    $offers = new OfferController($container);
    $technicians = new TechnicianController($container);
    $repairs = new RepairController($container);
    $messages = new MessageController($container);
    $notifications = new NotificationController($container);
    $activity = new ActivityController($container);
    $uuid = '{id:[0-9a-fA-F-]{36}}';

    // Illustrative landing page. It works before any service is connected.
    $app->get('/', new HomeController());

    $app->group('', function (Group $live) use ($guards, $auth, $account, $listings, $exchanges, $offers, $technicians, $repairs, $messages, $notifications, $activity, $uuid): void {
        $live->get('/login', $auth->showLogin(...));
        $live->post('/login', $auth->login(...));
        $live->get('/register', $auth->showRegister(...));
        $live->post('/register', $auth->register(...));
        $live->post('/logout', $auth->logout(...));

        $live->group('', function (Group $signedIn) use ($auth): void {
            $signedIn->get('/onboarding', $auth->showOnboarding(...));
            $signedIn->post('/onboarding', $auth->onboard(...));
        })->add($guards->signedIn());

        // Everything below needs a signed-in user with a finished profile.
        $live->group('', function (Group $member) use ($guards, $account, $listings, $exchanges, $offers, $technicians, $repairs, $messages, $notifications, $activity, $uuid): void {
            $member->get('/account', $account->edit(...));
            $member->post('/account', $account->update(...));

            $member->get('/market/new', $listings->create(...));
            $member->post('/market', $listings->store(...));
            $member->get("/market/{$uuid}/edit", $listings->edit(...));
            $member->post("/market/{$uuid}", $listings->update(...));
            $member->post("/market/{$uuid}/status", $listings->setStatus(...));
            $member->post("/market/{$uuid}/inquire", $listings->inquire(...));

            $member->get('/exchange/new', $exchanges->create(...));
            $member->post('/exchange', $exchanges->store(...));
            $member->get("/exchange/{$uuid}/edit", $exchanges->edit(...));
            $member->post("/exchange/{$uuid}", $exchanges->update(...));
            $member->post("/exchange/{$uuid}/remove", $exchanges->remove(...));
            $member->post("/exchange/{$uuid}/complete", $exchanges->complete(...));
            $member->post("/exchange/{$uuid}/cancel-agreement", $exchanges->cancelAgreement(...));
            $member->post("/exchange/{$uuid}/offers", $exchanges->submitOffer(...));

            $member->get("/offers/{$uuid}", $offers->show(...));
            $member->post("/offers/{$uuid}/accept", $offers->accept(...));
            $member->post("/offers/{$uuid}/decline", $offers->decline(...));
            $member->post("/offers/{$uuid}/counter", $offers->counter(...));
            $member->post("/offers/{$uuid}/withdraw", $offers->withdraw(...));

            $member->get("/repair/request/{$uuid}", $repairs->create(...));
            $member->post("/repair/request/{$uuid}", $repairs->store(...));
            $member->get('/repairs', $repairs->index(...));
            $member->get("/repairs/{$uuid}", $repairs->show(...));
            $member->post("/repairs/{$uuid}/quotations", $repairs->quote(...));
            $member->post("/repairs/{$uuid}/quotations/{quotation}", $repairs->decideQuotation(...));
            $member->post("/repairs/{$uuid}/added-costs", $repairs->requestAddedCost(...));
            $member->post("/repairs/{$uuid}/added-costs/{cost}", $repairs->decideAddedCost(...));
            $member->post("/repairs/{$uuid}/added-costs/{cost}/withdraw", $repairs->withdrawAddedCost(...));
            $member->post("/repairs/{$uuid}/status", $repairs->updateStatus(...));
            $member->post("/repairs/{$uuid}/cancel", $repairs->cancel(...));
            $member->post("/repairs/{$uuid}/review", $repairs->review(...));

            $member->group('/technician', function (Group $technician) use ($technicians, $uuid): void {
                $technician->get('/dashboard', $technicians->dashboard(...));
                $technician->get('/profile', $technicians->editProfile(...));
                $technician->post('/profile', $technicians->updateProfile(...));
                $technician->get('/services', $technicians->services(...));
                $technician->post('/services', $technicians->saveService(...));
                $technician->post("/services/{$uuid}", $technicians->saveService(...));
                $technician->post("/services/{$uuid}/active", $technicians->setServiceActive(...));
            })->add($guards->technician());

            $member->get('/messages', $messages->index(...));
            $member->get("/messages/{$uuid}", $messages->show(...));
            $member->post("/messages/{$uuid}", $messages->send(...));

            $member->get('/notifications', $notifications->index(...));
            $member->post('/notifications/read-all', $notifications->readAll(...));
            $member->post("/notifications/{$uuid}/open", $notifications->open(...));

            $member->get('/activity', $activity->index(...));
        })->add($guards->hasProfile())->add($guards->signedIn());

        // Public browsing. Fixed paths such as /market/new are registered first above.
        $live->get('/market', $listings->index(...));
        $live->get("/market/{$uuid}", $listings->show(...));
        $live->get('/exchange', $exchanges->index(...));
        $live->get("/exchange/{$uuid}", $exchanges->show(...));
        $live->get('/repair', $technicians->index(...));
        $live->get("/technicians/{$uuid}", $technicians->show(...));
    })->add($guards->configured());
};
