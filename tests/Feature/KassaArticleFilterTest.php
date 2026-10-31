<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KassaArticleFilterTest extends TestCase
{
    public function test_article_filter_orders_by_global_operation_count_and_displays_counts(): void
    {
        $this->actingAs(new User(['id' => 1, 'is_admin' => true, 'is_active' => true]));

        $expected = DB::table('legal.kassa_article as article')
            ->leftJoin('legal.cash_entries as entry', 'entry.article_id', '=', 'article.article_id')
            ->groupBy('article.article_id', 'article.article')
            ->orderByRaw('COUNT(entry.cash_entry_id) DESC')
            ->orderBy('article.article')
            ->select('article.article_id')
            ->selectRaw('COUNT(entry.cash_entry_id) as usage_count')
            ->get();
        $this->assertNotEmpty($expected);

        // An empty date range must not change the global article frequencies.
        $response = $this->get(route('kassa.index', ['date_from' => '2999-01-01']))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new DOMXPath($document);
        $inputs = $xpath->query('//*[@data-placeholder="Все статьи"]//input[@data-multi-select-option]');
        $this->assertCount($expected->count(), $inputs);

        foreach ($expected as $index => $article) {
            $input = $inputs->item($index);
            $this->assertSame((string) $article->article_id, $input->getAttribute('value'));
            $count = $xpath->query('.//span[contains(@class, "shrink-0 whitespace-nowrap text-gray-500")]', $input->parentNode);
            $this->assertSame(number_format((int) $article->usage_count, 0, ',', ' '), trim($count->item(0)->textContent));
        }
    }
}
