<?php

namespace App\Console\Commands;

use App\Services\Layers\CashLayerBuilder;
use Illuminate\Console\Command;

class RebuildCashLayer extends Command
{
    protected $signature = 'cash-layer:rebuild';

    protected $description = 'Rebuild cash operation interpretation layer from manual cash records and bank rules.';

    public function handle(CashLayerBuilder $builder): int
    {
        $count = $builder->rebuild();

        $this->info("Cash layer rebuilt: {$count} entry(s).");

        return self::SUCCESS;
    }
}
