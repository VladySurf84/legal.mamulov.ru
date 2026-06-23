<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Services\Signing\CryptoProCertificateImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\View\View;

class ElectronicSignatureController extends Controller
{
    public function index(): View
    {
        $credentials = ApiCredential::query()
            ->from('legal.api_credentials as c')
            ->leftJoin('legal.legal_own as legal', function ($join): void {
                $join
                    ->on('legal.legal_id', '=', 'c.owner_id')
                    ->where('c.owner_type', 'legal');
            })
            ->whereIn('c.credential_type', ['cryptopro_thumbprint', 'certificate_thumbprint'])
            ->orderBy('legal.legal_name')
            ->orderBy('c.name')
            ->get([
                'c.api_credential_id',
                'c.provider',
                'c.credential_type',
                'c.owner_type',
                'c.owner_id',
                'c.name',
                'c.encrypted_secret',
                'c.meta',
                'c.status',
                'c.last_used_at',
                'legal.legal_name',
                'legal.legal_inn',
            ])
            ->map(function (ApiCredential $credential): object {
                $secret = $credential->secretPayload();
                $thumbprint = (string) ($secret['thumbprint'] ?? $secret['secret'] ?? '');

                return (object) [
                    'api_credential_id' => $credential->api_credential_id,
                    'provider' => $credential->provider,
                    'credential_type' => $credential->credential_type,
                    'owner_type' => $credential->owner_type,
                    'owner_id' => $credential->owner_id,
                    'name' => $credential->name,
                    'status' => $credential->status,
                    'last_used_at' => $credential->last_used_at,
                    'legal_name' => $credential->legal_name,
                    'legal_inn' => $credential->legal_inn,
                    'thumbprint_tail' => $thumbprint !== '' ? mb_substr($thumbprint, -12) : null,
                    'subject' => $credential->meta['subject'] ?? $credential->meta['dn'] ?? null,
                    'valid_to' => $credential->meta['valid_to'] ?? $credential->meta['not_after'] ?? null,
                    'container' => $credential->meta['container'] ?? null,
                ];
            });

        return view('electronic-signatures.index', [
            'signatures' => $credentials,
            'signaturesCount' => DB::table('legal.api_credentials')
                ->whereIn('credential_type', ['cryptopro_thumbprint', 'certificate_thumbprint'])
                ->count(),
        ]);
    }

    public function import(CryptoProCertificateImporter $importer): RedirectResponse
    {
        try {
            $summary = $importer->import();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', sprintf(
            'Импорт CryptoPro завершен: найдено %d, добавлено %d, обновлено %d, пропущено %d.',
            $summary['found'],
            $summary['imported'],
            $summary['updated'],
            $summary['skipped'],
        ));
    }
}
