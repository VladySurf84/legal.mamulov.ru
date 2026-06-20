<?php

namespace App\Console\Commands;

use App\Services\Layers\MoneyLayerBuilder;
use Illuminate\Console\Command;

class RebuildMoneyLayer extends Command
{
    protected $signature = 'money-layer:rebuild';

    protected $description = 'Rebuild money movement interpretation layer from normalized documents.';

    public function handle(MoneyLayerBuilder $builder): int
    {
        $count = $builder->rebuild();

        $this->info("Money layer rebuilt: {$count} edge(s).");

        return self::SUCCESS;
    }
}
