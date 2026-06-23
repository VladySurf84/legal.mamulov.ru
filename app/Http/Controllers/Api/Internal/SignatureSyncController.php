<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Services\EdoLight\CryptoProSigner;
use App\Services\Signing\CryptoProCertificateImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SignatureSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $credentials = $this->signatureQuery()
            ->when($request->filled('legal_id'), function (Builder $query) use ($request): void {
                $legalId = (string) $request->query('legal_id');

                $query->where(function (Builder $query) use ($legalId): void {
                    $query
                        ->where('c.owner_id', $legalId)
                        ->orWhere('legal.legal_id', $legalId);
                });
            })
            ->when($request->filled('legal_inn'), function (Builder $query) use ($request): void {
                $legalInn = (string) $request->query('legal_inn');

                $query->where(function (Builder $query) use ($legalInn): void {
                    $query
                        ->where('legal.legal_inn', $legalInn)
                        ->orWhere('c.meta->legal_inn', $legalInn);
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('c.status', (string) $request->query('status'));
            })
            ->when($request->filled('subject_type'), function (Builder $query) use ($request): void {
                $query->where('c.meta->subject_type', (string) $request->query('subject_type'));
            })
            ->when($request->filled('changed_since'), function (Builder $query) use ($request): void {
                $query->where('c.updated_at', '>=', (string) $request->query('changed_since'));
            })
            ->orderBy('legal.legal_name')
            ->orderBy('c.name')
            ->get();

        return response()->json([
            'data' => $credentials->map(fn (ApiCredential $credential): array => $this->signaturePayload($credential))->values(),
            'meta' => [
                'count' => $credentials->count(),
                'server_time' => now()->toISOString(),
            ],
        ]);
    }

    public function show(int $signature, Request $request): JsonResponse
    {
        $credential = $this->signatureQuery()
            ->where('c.api_credential_id', $signature)
            ->firstOrFail();

        return response()->json([
            'data' => $this->signaturePayload($credential),
            'meta' => [
                'server_time' => now()->toISOString(),
            ],
        ]);
    }

    public function import(Request $request, CryptoProCertificateImporter $importer): JsonResponse
    {
        $summary = $importer->import();
        $credentials = $this->signatureQuery()
            ->orderBy('legal.legal_name')
            ->orderBy('c.name')
            ->get();

        return response()->json([
            'data' => $credentials->map(fn (ApiCredential $credential): array => $this->signaturePayload($credential))->values(),
            'meta' => [
                'import' => $summary,
                'count' => $credentials->count(),
                'server_time' => now()->toISOString(),
            ],
        ]);
    }

    public function sign(int $signature, Request $request, CryptoProSigner $signer): JsonResponse
    {
        $validated = $request->validate([
            'data' => ['required', 'string'],
            'data_encoding' => ['nullable', 'in:utf8,base64'],
            'detached' => ['nullable', 'boolean'],
        ]);

        $credential = $this->signatureQuery()
            ->where('c.api_credential_id', $signature)
            ->where('c.status', 'active')
            ->firstOrFail();

        $secret = $credential->secretPayload();
        $thumbprint = (string) ($secret['thumbprint'] ?? $secret['secret'] ?? '');

        if ($thumbprint === '') {
            throw ValidationException::withMessages([
                'signature' => 'Signature credential does not contain a CryptoPro thumbprint.',
            ]);
        }

        $data = (string) $validated['data'];

        if (($validated['data_encoding'] ?? 'utf8') === 'base64') {
            $decoded = base64_decode($data, true);

            if ($decoded === false) {
                throw ValidationException::withMessages([
                    'data' => 'The data field must contain valid base64.',
                ]);
            }

            $data = $decoded;
        }

        try {
            $signatureBody = $signer->sign(
                data: $data,
                thumbprint: $thumbprint,
                detached: (bool) ($validated['detached'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'data' => [
                'signature_id' => $credential->api_credential_id,
                'signature' => $signatureBody,
                'signature_encoding' => 'base64',
                'detached' => (bool) ($validated['detached'] ?? false),
                'signed_at' => now()->toISOString(),
            ],
        ]);
    }

    private function signatureQuery(): Builder
    {
        return ApiCredential::query()
            ->from('legal.api_credentials as c')
            ->leftJoin('legal.legal_own as legal', function ($join): void {
                $join
                    ->on('legal.legal_id', '=', 'c.owner_id')
                    ->where('c.owner_type', 'legal');
            })
            ->whereIn('c.credential_type', ['cryptopro_thumbprint', 'certificate_thumbprint', 'remote_certificate_thumbprint'])
            ->select([
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
                'c.created_at',
                'c.updated_at',
                DB::raw('legal.legal_name as owner_legal_name'),
                DB::raw('legal.legal_inn as owner_legal_inn'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function signaturePayload(ApiCredential $credential): array
    {
        $secret = $credential->secretPayload();
        $thumbprint = strtolower((string) ($secret['thumbprint'] ?? $secret['secret'] ?? $credential->meta['thumbprint'] ?? ''));
        $subject = $credential->meta['subject'] ?? $credential->meta['dn'] ?? null;
        $subjectType = (string) ($credential->meta['subject_type'] ?? CryptoProCertificateImporter::classifySubject($subject));

        return [
            'signature_id' => $credential->api_credential_id,
            'provider' => $credential->provider,
            'credential_type' => $credential->credential_type,
            'name' => $credential->name,
            'status' => $credential->status,
            'owner' => [
                'type' => $credential->owner_type,
                'id' => $credential->owner_id,
                'legal_name' => $credential->owner_legal_name,
                'legal_inn' => $credential->owner_legal_inn ?? $credential->meta['legal_inn'] ?? null,
            ],
            'thumbprint' => $thumbprint !== '' ? $thumbprint : null,
            'thumbprint_tail' => $thumbprint !== '' ? mb_substr($thumbprint, -12) : null,
            'subject' => $subject,
            'subject_type' => $subjectType,
            'subject_type_label' => CryptoProCertificateImporter::subjectTypeLabel($subjectType),
            'issuer' => $credential->meta['issuer'] ?? null,
            'serial' => $credential->meta['serial'] ?? null,
            'legal_inn' => $credential->meta['legal_inn'] ?? $credential->owner_legal_inn ?? null,
            'ogrnip' => $credential->meta['ogrnip'] ?? null,
            'ogrn' => $credential->meta['ogrn'] ?? null,
            'snils' => $credential->meta['snils'] ?? null,
            'valid_from' => $credential->meta['valid_from'] ?? $credential->meta['not_before'] ?? null,
            'valid_to' => $credential->meta['valid_to'] ?? $credential->meta['not_after'] ?? null,
            'container' => $credential->meta['container'] ?? null,
            'last_used_at' => $credential->last_used_at?->toISOString(),
            'created_at' => $credential->created_at?->toISOString(),
            'updated_at' => $credential->updated_at?->toISOString(),
        ];
    }
}
