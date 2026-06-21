<?php

namespace App\Console\Commands;

use App\Models\ApiCredential;
use Illuminate\Console\Command;

class SetApiCredential extends Command
{
    protected $signature = 'api-credential:set
        {provider : API provider, for example tinkoff}
        {owner_type : Owner type, for example bank_account}
        {owner_id : Owner id, for example bank_account_id}
        {secret : API token or key}
        {--type=api_token : Credential type}
        {--name= : Human-readable credential name}';

    protected $description = 'Create or update encrypted API credential.';

    public function handle(): int
    {
        $provider = (string) $this->argument('provider');
        $ownerType = (string) $this->argument('owner_type');
        $ownerId = (string) $this->argument('owner_id');
        $type = (string) $this->option('type');
        $now = now();

        $credential = ApiCredential::query()
            ->where('provider', $provider)
            ->where('credential_type', $type)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->first();

        $attributes = [
            'provider' => $provider,
            'credential_type' => $type,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'name' => $this->option('name') ?: null,
            'encrypted_secret' => ApiCredential::encryptSecret((string) $this->argument('secret')),
            'status' => 'active',
            'updated_at' => $now,
        ];

        if ($credential === null) {
            $attributes['created_at'] = $now;
            $credential = ApiCredential::query()->create($attributes);
            $this->info("Created API credential #{$credential->api_credential_id}.");
        } else {
            $credential->update($attributes);
            $this->info("Updated API credential #{$credential->api_credential_id}.");
        }

        return self::SUCCESS;
    }
}
