<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\User;
use Tests\TestCase;

class CounterpartyFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new User(['id' => 1, 'is_admin' => true, 'is_active' => true]));
    }

    public function test_compact_filters_preserve_selected_legal_entity(): void
    {
        $legalId = LegalEntity::query()->value('legal_id');
        if ($legalId === null) {
            $this->markTestSkipped('No legal entities available.');
        }
        $response = $this->get(route('counterparties.index', ['legal_id' => $legalId, 'contractor_inn' => '000000000000']))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $selected = $xpath->query('//*[@id="counterparties-filters"]//input[@name="legal_id" and @checked]');
        $this->assertCount(1, $selected);
        $this->assertSame((string) $legalId, $selected->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//*[@id="counterparties-filters"]//*[@data-ui-animated-select-dropdown]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="counterparties-filters"]//input[@name="contractor_inn" and @data-ui-table-filter-input]')->length);
        $this->assertStringContainsString('new FormData(filterForm)', $response->getContent());
    }

    public function test_ajax_header_matches_legal_entity_filter(): void
    {
        foreach ([null, '771505302376'] as $legalId) {
            $response = $this->getJson(route('counterparties.index', [
                'legal_id' => $legalId, 'contractor_inn' => '000000000000',
            ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
                ->assertJsonStructure(['head_html', 'html', 'summary_html', 'sticky_summary_html']);
            $this->assertSame($legalId === null, str_contains($response->json('head_html'), 'Наших юрлиц'));
        }
    }
}
