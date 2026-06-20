<?php

namespace App\Console\Commands;

use App\Services\Layers\VatLayerBuilder;
use Illuminate\Console\Command;

class RebuildVatLayer extends Command
{
    protected $signature = 'vat-layer:rebuild';

    protected $description = 'Rebuild VAT interpretation layer from active accountant VAT books.';

    public function handle(VatLayerBuilder $builder): int
    {
        $count = $builder->rebuild();

        $this->info("VAT layer rebuilt: {$count} event(s).");

        return self::SUCCESS;
    }
}
