<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Container;
use SwapFix\Application\Ui;

final class UiTest extends TestCase
{
    public function testDatesUseTheApplicationTimezone(): void
    {
        $ui = new Ui(new Container(['timezone' => 'Asia/Manila'], []), (new ServerRequestFactory())->createServerRequest('GET', '/'));
        self::assertSame('Nov 19, 2026', $ui->date('2026-11-18T18:00:00Z'));
        self::assertSame('Nov 19, 2026 2:00 AM', $ui->dateTime('2026-11-18T18:00:00Z'));
        self::assertSame('', $ui->date(null));
    }
}
