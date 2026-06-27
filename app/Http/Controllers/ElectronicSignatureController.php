<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Services\Signing\CryptoProCertificateImporter;
use App\Services\Signing\RemoteSignatureSyncer;
use App\Services\Signing\SignatureSyncApiClient;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ElectronicSignatureController extends Controller
{
    private const SIGNATURE_TYPES = [
        'cryptopro_thumbprint',
        'certificate_thumbprint',
        'remote_certificate_thumbprint',
    ];

    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewElectronicSignatures($request->user()), 403);

        $credentials = ApiCredential::query()
            ->from('legal.api_credentials as c')
            ->leftJoin('legal.legal_own as legal', function ($join): void {
                $join
                    ->on('legal.legal_id', '=', 'c.owner_id')
                    ->where('c.owner_type', 'legal');
            })
            ->whereIn('c.credential_type', self::SIGNATURE_TYPES)
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
                $subject = $credential->meta['subject'] ?? $credential->meta['dn'] ?? null;
                $subjectType = (string) ($credential->meta['subject_type'] ?? CryptoProCertificateImporter::classifySubject($subject));

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
                    'subject' => $subject,
                    'subject_type' => $subjectType,
                    'subject_type_label' => CryptoProCertificateImporter::subjectTypeLabel($subjectType),
                    'ogrnip' => $credential->meta['ogrnip'] ?? null,
                    'ogrn' => $credential->meta['ogrn'] ?? null,
                    'snils' => $credential->meta['snils'] ?? null,
                    'valid_to' => $credential->meta['valid_to'] ?? $credential->meta['not_after'] ?? null,
                    'container' => $credential->meta['container'] ?? null,
                ];
            });

        return view('electronic-signatures.index', [
            'signatures' => $credentials,
            'signaturesCount' => DB::table('legal.api_credentials')
                ->whereIn('credential_type', self::SIGNATURE_TYPES)
                ->count(),
            'canManageElectronicSignatures' => UserAccess::canManageElectronicSignatures($request->user()),
        ]);
    }

    public function import(Request $request, SignatureSyncApiClient $client, RemoteSignatureSyncer $syncer): RedirectResponse
    {
        abort_unless(UserAccess::canManageElectronicSignatures($request->user()), 403);

        try {
            $payload = $client->import();
            $syncSummary = $syncer->sync(is_array($payload['data'] ?? null) ? $payload['data'] : []);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $importSummary = is_array($payload['meta']['import'] ?? null) ? $payload['meta']['import'] : [];

        return back()->with('status', sprintf(
            'Импорт CryptoPro через API завершен: на сервере найдено %d, добавлено %d, обновлено %d, пропущено %d. Локально синхронизировано %d, создано %d, обновлено %d.',
            $importSummary['found'] ?? 0,
            $importSummary['imported'] ?? 0,
            $importSummary['updated'] ?? 0,
            $importSummary['skipped'] ?? 0,
            $syncSummary['synced'],
            $syncSummary['created'],
            $syncSummary['updated'],
        ));
    }
}
