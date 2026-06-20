<?php

namespace App\Console\Commands;

use App\Services\Layers\BankVatLayerBuilder;
use Illuminate\Console\Command;

class RebuildBankVatLayer extends Command
{
    protected $signature = 'vat-layer:rebuild-bank';

    protected $description = 'Rebuild VAT interpretation layer from bank payment purposes.';

    public function handle(BankVatLayerBuilder $builder): int
    {
        $count = $builder->rebuild();

        $this->info("Bank VAT layer rebuilt: {$count} event(s).");

        return self::SUCCESS;
    }
}
