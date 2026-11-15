<?php

namespace App\Services\ExchangeRates;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class BaitushumExchangeRateParser
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
        $class = static fn ($name) => 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")';
        $quotes = [];
        foreach (['cash' => 'cash', 'cashless' => 'non_cash', 'btb24' => 'mobile', 'nbkr' => 'official'] as $id => $type) {
            $rows = $xpath->query('//*[@id="btbRatesContent"]//*[@id="' . $id . '"]//li[div[' . $class('rate-name') . ']]');
            if ($rows->length === 0) {
                throw new RuntimeException("Baitushum payload is missing {$type} rates.");
            }
            $seen = [];
            foreach ($rows as $row) {
                $read = fn ($name) => trim($xpath->evaluate('string(./div[' . $class($name) . '])', $row));
                // The CNY row on the bank website has a kzt CSS class.
                $currency = strtoupper($read('rate-name'));
                if (! preg_match('/^[A-Z]{3}$/', $currency) || isset($seen[$currency])) {
                    throw new RuntimeException('Baitushum payload contains an invalid currency row.');
                }
                $seen[$currency] = true;
                $buy = $type === 'official' ? null : $this->decimal($read('rate-buy'));
                $sell = $type === 'official' ? null : $this->decimal($read('rate-sell'));
                $official = $type === 'official' ? $this->decimal($read('rate-buy')) : null;
                $quotes[] = compact('currency', 'type', 'buy', 'sell', 'official') + ['raw' => [
                    'section' => $id,
                    'date' => trim($xpath->evaluate('string(//*[@id="btbRatesContent"]//*[@id="' . $id . '"]//li[' . $class('head') . ']/div[1])')),
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
            throw new RuntimeException('Baitushum payload contains an invalid rate.');
        }

        return $value;
    }
}
