<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ServerRequestInterface;
use SwapFix\Support\DomainError;
use SwapFix\Support\Money;
use SwapFix\Support\Uuid;

/** Typed access to submitted form or query values. */
final class Input
{
    public function __construct(private readonly array $values) {}

    public static function body(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        return new self(is_array($body) ? $body : []);
    }

    public static function query(ServerRequestInterface $request): self
    {
        return new self($request->getQueryParams());
    }

    public function all(): array
    {
        return $this->values;
    }

    public function text(string $key, int $max = 4000): string
    {
        $value = $this->values[$key] ?? '';
        return is_string($value) ? mb_substr(trim(str_replace("\0", '', $value)), 0, $max) : '';
    }

    public function choice(string $key, array $allowed, string $default = ''): string
    {
        $value = $this->text($key, 40);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function flag(string $key): bool
    {
        return in_array($this->values[$key] ?? '', ['1', 'on', 'true'], true);
    }

    public function int(string $key, int $default, int $min, int $max): int
    {
        $value = filter_var($this->values[$key] ?? null, FILTER_VALIDATE_INT);
        return $value === false ? $default : min($max, max($min, $value));
    }

    /** Splits comma or newline separated values. */
    public function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $this->text($key, 4000)) ?: []), static fn (string $item): bool => $item !== ''));
    }

    public function strings(string $key): array
    {
        $value = $this->values[$key] ?? [];
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public function uuid(string $key): ?string
    {
        $value = $this->text($key, 36);
        return Uuid::valid($value) ? strtolower($value) : null;
    }

    public function money(string $key, string $label, bool $required = true): ?string
    {
        $value = $this->text($key, 20);
        if ($value === '' && !$required) {
            return null;
        }
        return Money::parse($value) ?? throw new DomainError($label . ' must be a valid amount with up to two decimals.');
    }

    public function optionalMoney(string $key): ?string
    {
        $value = $this->text($key, 20);
        return $value === '' ? null : Money::parse($value);
    }

    public function date(string $key, string $label, bool $required = true): ?string
    {
        $value = $this->text($key, 10);
        if ($value === '' && !$required) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new DomainError($label . ' must be a valid date.');
        }
        return $value;
    }
}
