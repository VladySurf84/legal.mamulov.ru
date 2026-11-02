<?php

namespace Tests\Feature;

use App\Services\Layers\BankVatLayerBuilder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BankVatLayerRebuildTest extends TestCase
{
    public function test_rebuild_serializes_writes_and_replaces_only_bank_vat_events(): void
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        DB::shouldReceive('statement')->once()->with('LOCK TABLE legal.vat_events IN SHARE ROW EXCLUSIVE MODE')->ordered()->andReturn(true);
        $query = Mockery::mock();
        DB::shouldReceive('table')->once()->with('legal.vat_events')->ordered()->andReturn($query);
        $query->shouldReceive('where')->once()->with('source_system', 'bank_payment_vat')->andReturnSelf();
        $query->shouldReceive('delete')->once()->andReturn(0);
        DB::shouldReceive('select')->once()->with(Mockery::type('string'))->ordered()->andReturn([]);

        $this->assertSame(0, app(BankVatLayerBuilder::class)->rebuild());
    }
}
