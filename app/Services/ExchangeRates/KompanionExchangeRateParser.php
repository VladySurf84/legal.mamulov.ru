<?php

namespace App\Services\ExchangeRates;

use RuntimeException;

class KompanionExchangeRateParser
{
    public function parse(string $body, int $type): array
    {
        $rows = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new RuntimeException('Kompanion exchange rates payload must be a non-empty list.');
        }

        foreach ($rows as $row) {
            if (! is_array($row)
                || ($row['type'] ?? null) !== $type
                || ($row['crossCurrencySymbol'] ?? null) !== 'KGS'
                || ! preg_match('/^[A-Z]{3}$/', $row['currencySymbol'] ?? '')
                || $row['currencySymbol'] === 'KGS'
                || ! is_numeric($row['buyRate'] ?? null)
                || ! is_numeric($row['sellRate'] ?? null)
                || $row['buyRate'] <= 0 || $row['sellRate'] <= 0) {
                throw new RuntimeException('Kompanion exchange rates payload contains an invalid quote.');
            }
        }

        return $rows;
    }
}
