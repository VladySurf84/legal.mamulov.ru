<?php

namespace App\Console\Commands;

class ImportOzonBankStatement extends ImportBankStatement
{
    protected $signature = 'ozon-bank:import-statement
        {file : Path to 1CClientBankExchange statement file}
        {--bank-id= : Bank BIC. By default it is resolved from legal.bank_account}
        {--rebuild-money-layer : Rebuild money interpretation layer after import}';

    protected $description = 'Alias for bank-statement:import-1c.';
}
