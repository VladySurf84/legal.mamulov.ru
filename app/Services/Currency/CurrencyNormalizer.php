<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CurrencyNormalizer
{
    public const DEFAULT_CURRENCY_CODE = '643';

    /** @var array<string, string> */
    private array $cache = [];

    public function normalize(mixed $value, string $default = self::DEFAULT_CURRENCY_CODE): string
    {
        $normalized = $this->normalizeNullable($value);

        if ($normalized !== null) {
            return $normalized;
        }

        return $this->normalizeNullable($default) ?? $default;
    }

    public function normalizeNullable(mixed $value): ?string
    {
        $alias = $this->alias($value);

        if ($alias === null) {
            return null;
        }

        if (array_key_exists($alias, $this->cache)) {
            return $this->cache[$alias];
        }

        $currencyCode = DB::table('legal.currency_aliases')
            ->where('currency_alias', $alias)
            ->value('currency_code');

        if ($currencyCode === null) {
            throw new RuntimeException("Currency '{$alias}' was not found in legal.currency_aliases.");
        }

        return $this->cache[$alias] = trim((string) $currencyCode);
    }

    private function alias(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}$/', $value) === 1) {
            return str_pad($value, 3, '0', STR_PAD_LEFT);
        }

        return $value;
    }
}
