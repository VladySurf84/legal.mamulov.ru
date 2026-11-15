<?php

namespace App\Services\ExchangeRates;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class CapitalBankExchangeRateParser
{
    public function parse(string $body): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $quotes = [];
        foreach (['div1' => 'cash', 'div2' => 'non_cash', 'div3' => 'official'] as $id => $type) {
            $rows = $xpath->query('//*[@id="' . $id . '"]//table//tr[td/strong]');
            if ($rows->length === 0) {
                throw new RuntimeException("Capital Bank payload is missing {$type} rates.");
            }
            $seen = [];
            foreach ($rows as $row) {
                $cells = $xpath->query('./td', $row);
                $currency = trim($xpath->evaluate('string(./td/strong)', $row));
                if ($cells->length !== 5 || ! preg_match('/^[A-Z]{3}$/', $currency) || isset($seen[$currency])) {
                    throw new RuntimeException('Capital Bank payload contains an invalid currency row.');
                }
                $seen[$currency] = true;
                $values = [];
                foreach ($cells as $cell) {
                    $values[] = trim($cell->textContent);
                }
                $buy = $type === 'official' ? null : $this->decimal($values[2]);
                $sell = $type === 'official' ? null : $this->decimal($values[4]);
                $official = $type === 'official' ? $this->decimal($values[4]) : null;
                $quotes[] = compact('currency', 'type', 'buy', 'sell', 'official') + ['raw' => [
                    'section' => $id,
                    'cells' => $values,
                    'html' => $document->saveHTML($row),
                ]];
            }
        }

        return $quotes;
    }

    private function decimal(string $value): string
    {
        $value = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $value);
        if (! is_numeric($value) || (float) $value <= 0) {
            throw new RuntimeException('Capital Bank payload contains an invalid rate.');
        }

        return $value;
    }
}
