<?php
declare(strict_types=1);

// Creates the demo sellers and technicians, with their listings, swaps and repair services, through the normal user-scoped API.
// New accounts are created only while Confirm email is off, so no message is ever sent to the made-up addresses.
// Usage: php scripts/seed-demo-sellers.php status|run [account keys, for example tech1 tech2]

use SwapFix\Application\Container;
use SwapFix\Support\DomainError;

function demoEmailConfirmationIsOff(Container $app): bool
{
    return ($app->client()->request('GET', '/auth/v1/settings')['mailer_autoconfirm'] ?? false) === true;
}

/** @return list<string> Listing and swap photos that are not on disk. */
function demoMissingImages(array $accounts, string $imageRoot): array
{
    $missing = [];
    foreach ($accounts as $account) {
        foreach ([...($account['listings'] ?? []), ...($account['exchanges'] ?? [])] as $item) {
            if (!is_file($imageRoot . '/' . $item['image'])) {
                $missing[] = $item['image'];
            }
        }
    }
    return $missing;
}

/**
 * Publishes items whose title is new and moves existing ones to the account's location, keeping their photos.
 * @return array{int, int} [published, updated]
 */
function demoSyncItems(Container $app, string $token, string $userId, string $location, string $imageRoot, array $items, array $rows, callable $save): array
{
    $existing = array_column($rows, null, 'title');
    $published = $updated = 0;
    foreach ($items as $item) {
        $row = $existing[$item['title']] ?? null;
        if ($row !== null) {
            if ($row['location'] !== $location) {
                $save($row['id'], ['location' => $location] + $item, $row['photo_paths']);
                $updated++;
            }
            continue;
        }
        $photo = $app->storage()->upload($token, $imageRoot . '/' . $item['image'], 'listing-images', $userId);
        try {
            $save(null, ['location' => $location] + $item, [$photo['path']]);
        } catch (Throwable $error) {
            $app->storage()->delete($token, 'listing-images', [$photo['path']]);
            throw $error;
        }
        $published++;
    }
    return [$published, $updated];
}

/** Safe to repeat: an existing account signs in and only new or moved items change. Returns [published, updated]. */
function demoSeedSeller(Container $app, array $seller, array &$login, string $imageRoot, callable $saveLogin): array
{
    if (empty($login['created'])) {
        $session = $app->auth()->signUp($seller['email'], $login['password']);
        if (!is_string($session['access_token'] ?? null)) {
            throw new RuntimeException('Sign-up did not return a session. Confirm email may still be on.');
        }
        $login['created'] = true;
        $saveLogin();
    } else {
        $session = $app->auth()->signIn($seller['email'], $login['password']);
    }
    $token = $session['access_token'];
    $userId = $session['user']['id'];
    try {
        $app->accounts()->createProfile($token, $seller['type'] ?? 'customer', $seller['name'], $seller['location']);
    } catch (DomainError $error) {
        if ($error->status !== 409) {
            throw $error;
        }
    }
    $app->accounts()->updateProfile($token, $seller['name'], $seller['location'], null, false, false);

    $published = $updated = 0;
    if (isset($seller['profile'])) {
        $technicians = $app->technicians();
        $technicians->saveProfile($token, ['location' => $seller['location']] + $seller['profile']);
        $offered = array_column($technicians->ownServices($token, $userId), 'name');
        foreach ($seller['services'] as $service) {
            if (!in_array($service['name'], $offered, true)) {
                $technicians->saveService($token, null, $service);
                $published++;
            }
        }
    }
    if (($seller['listings'] ?? []) !== []) {
        $listings = $app->listings();
        [$new, $moved] = demoSyncItems($app, $token, $userId, $seller['location'], $imageRoot, $seller['listings'],
            $app->activity()->listings($token, $userId, 200, 0),
            static fn (?string $id, array $fields, array $photos) => $listings->save($token, $id, $fields, $photos));
        $published += $new;
        $updated += $moved;
    }
    if (($seller['exchanges'] ?? []) !== []) {
        $exchanges = $app->exchanges();
        [$new, $moved] = demoSyncItems($app, $token, $userId, $seller['location'], $imageRoot, $seller['exchanges'],
            $app->activity()->exchanges($token, $userId, 200, 0),
            static fn (?string $id, array $fields, array $photos) => $exchanges->save($token, $id, $fields, $photos));
        $published += $new;
        $updated += $moved;
    }
    return [$published, $updated];
}

if (PHP_SAPI !== 'cli' || realpath($_SERVER['argv'][0] ?? '') !== __FILE__) {
    return;
}

require dirname(__DIR__) . '/bootstrap/app.php';
$app = new Container(require dirname(__DIR__) . '/config/app.php', require dirname(__DIR__) . '/config/services.php');
$command = $argv[1] ?? '';
$tools = dirname(__DIR__) . '/.tools';
$imageRoot = $tools . '/demo-seed';
$loginFile = $tools . '/demo-sellers.json';
$accounts = require __DIR__ . '/demo-sellers.php';
$only = array_slice($argv, 2);
if ($only !== []) {
    $accounts = array_intersect_key($accounts, array_flip($only));
}

try {
    $confirmationOff = demoEmailConfirmationIsOff($app);
    echo 'Confirm email: ' . ($confirmationOff ? 'off' : 'on') . PHP_EOL;
    if ($command !== 'run') {
        exit($command === 'status' ? 0 : 2);
    }
    $logins = is_file($loginFile) ? json_decode((string) file_get_contents($loginFile), true, flags: JSON_THROW_ON_ERROR) : [];
    $needsSignUp = array_filter(array_keys($accounts), static fn (string $key): bool => empty($logins[$key]['created']));
    if ($needsSignUp !== [] && !$confirmationOff) {
        throw new RuntimeException('Turn off Confirm email first to create new accounts. Nothing was changed.');
    }
    $missing = demoMissingImages($accounts, $imageRoot);
    if ($missing !== []) {
        throw new RuntimeException('Missing photos: ' . implode(', ', $missing));
    }
    $saveLogins = static function () use (&$logins, $loginFile): void {
        file_put_contents($loginFile, json_encode($logins, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    };
    foreach ($accounts as $key => $account) {
        $logins[$key] ??= ['email' => $account['email'], 'password' => 'Sf' . bin2hex(random_bytes(6)) . '!' . random_int(10, 99), 'created' => false];
        $saveLogins();
        [$published, $updated] = demoSeedSeller($app, $account, $logins[$key], $imageRoot, $saveLogins);
        echo $account['email'] . ': ' . $published . ' published, ' . $updated . ' moved to ' . $account['location'] . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
