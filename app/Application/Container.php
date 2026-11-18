<?php
declare(strict_types=1);

namespace SwapFix\Application;

use GuzzleHttp\ClientInterface;
use SwapFix\Http\Cookies;
use SwapFix\Http\SessionManager;
use SwapFix\Http\UserSession;
use SwapFix\Integrations\Mail\Mailer;
use SwapFix\Integrations\Supabase\Auth;
use SwapFix\Integrations\Supabase\Database;
use SwapFix\Integrations\Supabase\Storage;
use SwapFix\Integrations\Supabase\SupabaseClient;
use SwapFix\Modules\Accounts\AccountRepository;
use SwapFix\Modules\Activity\ActivityRepository;
use SwapFix\Modules\Exchange\ExchangeRepository;
use SwapFix\Modules\Marketplace\ListingRepository;
use SwapFix\Modules\Messaging\MessageRepository;
use SwapFix\Modules\Notifications\MailDispatcher;
use SwapFix\Modules\Notifications\NotificationRepository;
use SwapFix\Modules\Repair\RepairRepository;
use SwapFix\Modules\Repair\TechnicianRepository;
use SwapFix\Support\Crypto;

/** Lazily builds shared services for one application instance. */
final class Container
{
    private array $instances = [];

    public function __construct(
        public readonly array $config,
        public readonly array $services,
        private readonly ?ClientInterface $http = null,
    ) {}

    /** @return list<string> Names of required settings that are absent or malformed. */
    public function missingSettings(): array
    {
        return $this->instances['missing'] ??= $this->findMissingSettings();
    }

    public function ready(): bool
    {
        return $this->missingSettings() === [];
    }

    public function isProduction(): bool
    {
        return $this->config['environment'] === 'production';
    }

    public function isHttps(): bool
    {
        return str_starts_with($this->config['url'], 'https://');
    }

    public function crypto(): Crypto
    {
        return $this->instances[__FUNCTION__] ??= new Crypto($this->config['key']);
    }

    public function cookies(): Cookies
    {
        return $this->instances[__FUNCTION__] ??= new Cookies($this->isHttps());
    }

    public function client(): SupabaseClient
    {
        return $this->instances[__FUNCTION__] ??= new SupabaseClient($this->services['supabase']['url'], $this->services['supabase']['publishable_key'], $this->http);
    }

    public function auth(): Auth
    {
        return $this->instances[__FUNCTION__] ??= new Auth($this->client());
    }

    public function db(): Database
    {
        return $this->instances[__FUNCTION__] ??= new Database($this->client());
    }

    public function storage(): Storage
    {
        return $this->instances[__FUNCTION__] ??= new Storage($this->client(), $this->auth(), $this->services['supabase']['upload_max_bytes']);
    }

    public function sessions(): SessionManager
    {
        return $this->instances[__FUNCTION__] ??= new SessionManager($this->crypto(), $this->cookies(), $this->auth(), $this->db());
    }

    public function view(): View
    {
        return $this->instances[__FUNCTION__] ??= new View($this);
    }

    public function errors(): ErrorRenderer
    {
        return $this->instances[__FUNCTION__] ??= new ErrorRenderer($this);
    }

    public function uploads(UserSession $user): PhotoUploads
    {
        return new PhotoUploads($this->storage(), $user);
    }

    public function accounts(): AccountRepository
    {
        return $this->instances[__FUNCTION__] ??= new AccountRepository($this->db());
    }

    public function listings(): ListingRepository
    {
        return $this->instances[__FUNCTION__] ??= new ListingRepository($this->db());
    }

    public function exchanges(): ExchangeRepository
    {
        return $this->instances[__FUNCTION__] ??= new ExchangeRepository($this->db());
    }

    public function technicians(): TechnicianRepository
    {
        return $this->instances[__FUNCTION__] ??= new TechnicianRepository($this->db());
    }

    public function repairs(): RepairRepository
    {
        return $this->instances[__FUNCTION__] ??= new RepairRepository($this->db());
    }

    public function messages(): MessageRepository
    {
        return $this->instances[__FUNCTION__] ??= new MessageRepository($this->db());
    }

    public function notifications(): NotificationRepository
    {
        return $this->instances[__FUNCTION__] ??= new NotificationRepository($this->db());
    }

    public function activity(): ActivityRepository
    {
        return $this->instances[__FUNCTION__] ??= new ActivityRepository($this->db());
    }

    /** Null until the worker secret, cron secret and SMTP settings are all present. */
    public function mailDispatcher(): ?MailDispatcher
    {
        $worker = $this->services['mail_worker'] ?? [];
        $mail = $this->services['mail'];
        if (!$this->ready() || ($worker['secret_key'] ?? '') === '' || ($worker['cron_secret'] ?? '') === '' || $mail['host'] === '' || $mail['from_address'] === '') {
            return null;
        }
        $client = SupabaseClient::forServerJob($this->services['supabase']['url'], $worker['secret_key'], $this->http);
        return new MailDispatcher(new Database($client), (new Mailer($mail))->send(...), $this->config['url']);
    }

    private function findMissingSettings(): array
    {
        $missing = [];
        try {
            new Crypto($this->config['key'] ?? '');
        } catch (\InvalidArgumentException) {
            $missing[] = 'APP_KEY';
        }
        $supabase = $this->services['supabase'];
        foreach (['SUPABASE_URL' => $supabase['url'], 'SUPABASE_PUBLISHABLE_KEY' => $supabase['publishable_key']] as $name => $value) {
            if ($value === '') {
                $missing[] = $name;
            }
        }
        if ($supabase['url'] !== '' && $supabase['publishable_key'] !== '') {
            try {
                new SupabaseClient($supabase['url'], $supabase['publishable_key'], $this->http);
            } catch (\InvalidArgumentException) {
                array_push($missing, 'SUPABASE_URL', 'SUPABASE_PUBLISHABLE_KEY');
            }
        }
        return $missing;
    }
}
