<?php

namespace App\Services\Signing;

use App\Models\ApiCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class CryptoProCertificateImporter
{
    /**
     * @return array{found: int, imported: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $certmgrPath = (string) config('edo_light.certmgr_path');

        $result = Process::timeout(60)->run([$certmgrPath, '-list']);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'CryptoPro certificate list failed.');
        }

        return $this->storeCertificates($this->parse($result->output()));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parse(string $output): array
    {
        $certificates = [];
        $current = null;

        foreach (preg_split('/\R/u', $output) ?: [] as $line) {
            $line = rtrim($line);

            if (preg_match('/^\d+-+$/', $line) === 1) {
                if ($current !== null) {
                    $certificates[] = $current;
                }

                $current = [];
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^([^:]+?)\s*:\s*(.*)$/u', $line, $matches) !== 1) {
                continue;
            }

            $key = trim($matches[1]);
            $value = trim($matches[2]);

            if ($key !== '') {
                $current[$key] = $value;
            }
        }

        if ($current !== null) {
            $certificates[] = $current;
        }

        return array_values(array_filter(
            $certificates,
            fn (array $certificate): bool => ! empty($certificate['SHA1 Thumbprint'])
        ));
    }

    /**
     * @param  array<int, array<string, string>>  $certificates
     * @return array{found: int, imported: int, updated: int, skipped: int}
     */
    private function storeCertificates(array $certificates): array
    {
        $summary = [
            'found' => count($certificates),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($certificates as $certificate) {
            $thumbprint = strtolower(trim((string) ($certificate['SHA1 Thumbprint'] ?? '')));

            if ($thumbprint === '') {
                $summary['skipped']++;
                continue;
            }

            $subject = (string) ($certificate['Subject'] ?? '');
            $legalInn = $this->extractInn($subject);
            $legalId = $this->resolveLegalId($legalInn);
            $now = now();

            $credential = ApiCredential::query()
                ->where('provider', 'cryptopro')
                ->where('credential_type', 'certificate_thumbprint')
                ->where('meta->thumbprint', $thumbprint)
                ->first();

            $attributes = [
                'provider' => 'cryptopro',
                'credential_type' => 'certificate_thumbprint',
                'owner_type' => $legalId !== null ? 'legal' : null,
                'owner_id' => $legalId,
                'name' => $this->certificateName($certificate),
                'encrypted_secret' => ApiCredential::encryptSecret(json_encode([
                    'thumbprint' => $thumbprint,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'meta' => [
                    'thumbprint' => $thumbprint,
                    'subject' => $subject,
                    'issuer' => $certificate['Issuer'] ?? null,
                    'serial' => $certificate['Serial'] ?? null,
                    'subject_key_id' => $certificate['SubjectKeyID'] ?? null,
                    'signature_algorithm' => $certificate['Signature Algorithm'] ?? null,
                    'public_key_algorithm' => $certificate['PublicKey Algorithm'] ?? null,
                    'valid_from' => $certificate['Not valid before'] ?? null,
                    'valid_to' => $certificate['Not valid after'] ?? null,
                    'private_key_link' => $certificate['PrivateKey Link'] ?? null,
                    'container' => $certificate['Container'] ?? null,
                    'provider_name' => $certificate['Provider Name'] ?? null,
                    'provider_info' => $certificate['Provider Info'] ?? null,
                    'identification_kind' => $certificate['Identification Kind'] ?? null,
                    'legal_inn' => $legalInn,
                    'imported_at' => $now->toISOString(),
                ],
                'status' => 'active',
                'updated_at' => $now,
            ];

            if ($credential === null) {
                $attributes['created_at'] = $now;
                ApiCredential::query()->create($attributes);
                $summary['imported']++;
            } else {
                $credential->update($attributes);
                $summary['updated']++;
            }
        }

        return $summary;
    }

    private function extractInn(string $subject): ?string
    {
        if (preg_match('/(?:^|,\s*)ИНН(?:\s+ЮЛ)?=([0-9]{10,12})/u', $subject, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function resolveLegalId(?string $inn): ?string
    {
        if ($inn === null) {
            return null;
        }

        $legalId = DB::table('legal.legal_own')
            ->where('legal_id', $inn)
            ->orWhere('legal_inn', $inn)
            ->value('legal_id');

        return $legalId !== null ? (string) $legalId : null;
    }

    /**
     * @param  array<string, string>  $certificate
     */
    private function certificateName(array $certificate): string
    {
        $subject = (string) ($certificate['Subject'] ?? '');

        if (preg_match('/(?:^|,\s*)CN=([^,]+)/u', $subject, $matches) === 1) {
            return trim($matches[1], " \t\n\r\0\x0B\"");
        }

        return 'CryptoPro certificate';
    }
}
