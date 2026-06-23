<?php

namespace App\Services\Signing;

use App\Models\ApiCredential;
use Illuminate\Support\Facades\DB;

class RemoteSignatureSyncer
{
    /**
     * @param  array<int, array<string, mixed>>  $signatures
     * @return array{synced: int, created: int, updated: int, skipped: int}
     */
    public function sync(array $signatures): array
    {
        $summary = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($signatures as $signature) {
            $remoteId = (string) ($signature['signature_id'] ?? '');
            $thumbprint = strtolower((string) ($signature['thumbprint'] ?? ''));

            if ($remoteId === '' || $thumbprint === '') {
                $summary['skipped']++;
                continue;
            }

            $owner = is_array($signature['owner'] ?? null) ? $signature['owner'] : [];
            $legalId = $this->resolveLegalId(
                (string) ($owner['id'] ?? ''),
                (string) ($owner['legal_inn'] ?? $signature['legal_inn'] ?? ''),
            );

            $credential = ApiCredential::query()
                ->where('provider', 'legal_signature_api')
                ->where('credential_type', 'remote_certificate_thumbprint')
                ->where('meta->remote_signature_id', $remoteId)
                ->first();

            $now = now();
            $attributes = [
                'provider' => 'legal_signature_api',
                'credential_type' => 'remote_certificate_thumbprint',
                'owner_type' => $legalId !== null ? 'legal' : null,
                'owner_id' => $legalId,
                'name' => $signature['name'] ?? 'Remote CryptoPro certificate',
                'encrypted_secret' => ApiCredential::encryptSecret(json_encode([
                    'thumbprint' => $thumbprint,
                    'remote_signature_id' => $remoteId,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'meta' => [
                    'remote_signature_id' => $remoteId,
                    'remote_provider' => $signature['provider'] ?? null,
                    'remote_credential_type' => $signature['credential_type'] ?? null,
                    'remote_base_url' => rtrim((string) config('internal_api.signature_sync_base_url'), '/'),
                    'thumbprint' => $thumbprint,
                    'subject' => $signature['subject'] ?? null,
                    'subject_type' => $signature['subject_type'] ?? null,
                    'subject_type_label' => $signature['subject_type_label'] ?? null,
                    'issuer' => $signature['issuer'] ?? null,
                    'serial' => $signature['serial'] ?? null,
                    'legal_inn' => $owner['legal_inn'] ?? $signature['legal_inn'] ?? null,
                    'ogrnip' => $signature['ogrnip'] ?? null,
                    'ogrn' => $signature['ogrn'] ?? null,
                    'snils' => $signature['snils'] ?? null,
                    'valid_from' => $signature['valid_from'] ?? null,
                    'valid_to' => $signature['valid_to'] ?? null,
                    'container' => $signature['container'] ?? null,
                    'synced_at' => $now->toISOString(),
                ],
                'status' => $signature['status'] ?? 'active',
                'updated_at' => $now,
            ];

            if ($credential === null) {
                $attributes['created_at'] = $now;
                ApiCredential::query()->create($attributes);
                $summary['created']++;
            } else {
                $credential->update($attributes);
                $summary['updated']++;
            }

            $summary['synced']++;
        }

        return $summary;
    }

    private function resolveLegalId(string $ownerId, string $legalInn): ?string
    {
        if ($ownerId !== '') {
            $legalId = DB::table('legal.legal_own')
                ->where('legal_id', $ownerId)
                ->value('legal_id');

            if ($legalId !== null) {
                return (string) $legalId;
            }
        }

        if ($legalInn === '') {
            return null;
        }

        $legalId = DB::table('legal.legal_own')
            ->where('legal_id', $legalInn)
            ->orWhere('legal_inn', $legalInn)
            ->value('legal_id');

        return $legalId !== null ? (string) $legalId : null;
    }
}
