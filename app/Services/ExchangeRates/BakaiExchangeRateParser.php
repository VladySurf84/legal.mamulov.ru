<?php

namespace App\Services\ExchangeRates;

use RuntimeException;

class BakaiExchangeRateParser
{
    public function parse(string $body): array
    {
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $rows = $payload['result'] ?? null;

        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new RuntimeException('Bakai exchange rates payload must contain a non-empty result list.');
        }

        $quotes = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) !== 1) {
                throw new RuntimeException('Bakai exchange rates payload contains an invalid row.');
            }
            $currency = array_key_first($row);
            // Metals and ingots have different units and are not currency quotes.
            if (in_array($currency, ['XAU', 'XAG', 'XPT', 'XPD'], true)) {
                continue;
            }
            if (! preg_match('/^[A-Z]{3}$/', $currency) || ! is_array($row[$currency])) {
                throw new RuntimeException('Bakai exchange rates payload contains an invalid currency.');
            }
            foreach (['cash' => 'cash', 'non_cash' => 'non_cash', 'transfer' => 'transfer', 'nbkr' => 'official'] as $key => $type) {
                $values = $row[$currency][$key] ?? [];
                $buy = $type === 'official' ? null : ($values['buy'] ?? null);
                $sell = $type === 'official' ? null : ($values['sell'] ?? null);
                $official = $type === 'official' ? ($values['rate'] ?? null) : null;
                foreach ([$buy, $sell, $official] as $value) {
                    if ($value !== null && (! is_numeric($value) || $value <= 0)) {
                        throw new RuntimeException('Bakai exchange rates payload contains an invalid rate.');
                    }
                }
                if ($buy === null && $sell === null && $official === null) {
                    continue;
                }
                $quotes[] = [
                    'currency' => $currency,
                    'type' => $type,
                    'buy' => $buy,
                    'sell' => $sell,
                    'official' => $official,
                    'raw' => $row + ['latest_update_time' => $payload['latest_update_time'] ?? null],
                ];
            }
        }
        if ($quotes === []) {
            throw new RuntimeException('Bakai exchange rates payload contains no currency quotes.');
        }

        return $quotes;
    }
}
