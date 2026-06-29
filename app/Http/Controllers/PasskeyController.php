<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPasskey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use Throwable;

class PasskeyController extends Controller
{
    public function index(Request $request): View
    {
        $passkeys = $request->user()
            ->passkeys()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return view('passkeys.index', [
            'passkeys' => $passkeys,
        ]);
    }

    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $webAuthn = $this->webAuthn($request);
        $excludeCredentialIds = $user->passkeys()
            ->pluck('credential_id')
            ->map(fn (string $credentialId): string|false => base64_decode($credentialId, true))
            ->filter(fn (string|false $credentialId): bool => is_string($credentialId))
            ->values()
            ->all();

        $options = $webAuthn->getCreateArgs(
            (string) $user->getKey(),
            (string) $user->email,
            (string) ($user->name ?: $user->email),
            120,
            'preferred',
            'required',
            null,
            $excludeCredentialIds,
        );

        $request->session()->put(
            'passkey.registration.challenge',
            base64_encode($webAuthn->getChallenge()->getBinaryString()),
        );

        return response()->json($options);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'clientDataJSON' => ['required', 'string'],
            'attestationObject' => ['required', 'string'],
            'transports' => ['nullable', 'array'],
            'transports.*' => ['string', 'max:32'],
        ]);

        $challenge = $this->pullChallenge($request, 'passkey.registration.challenge');
        if (! $challenge) {
            return response()->json(['message' => 'Сессия регистрации ключа истекла.'], 419);
        }

        try {
            $webAuthn = $this->webAuthn($request);
            $credential = $webAuthn->processCreate(
                base64_decode($data['clientDataJSON'], true) ?: '',
                base64_decode($data['attestationObject'], true) ?: '',
                $challenge,
                true,
                true,
                false,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Не удалось зарегистрировать ключ входа.'], 422);
        }

        $passkey = $request->user()->passkeys()->create([
            'name' => $data['name'] ?: $this->defaultPasskeyName($request),
            'credential_id' => base64_encode($credential->credentialId),
            'credential_public_key' => $credential->credentialPublicKey,
            'signature_count' => $credential->signatureCounter,
            'transports' => array_values(array_unique($data['transports'] ?? [])),
        ]);

        return response()->json([
            'message' => 'Ключ входа добавлен.',
            'passkey' => [
                'id' => $passkey->getKey(),
                'name' => $passkey->name,
                'created_at' => $passkey->created_at?->format('d.m.Y H:i'),
            ],
        ]);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'email'],
        ]);

        $user = User::query()
            ->whereRaw('lower(email) = ?', [strtolower($data['login'])])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Пользователь не найден.'], 404);
        }

        $credentialIds = $user->passkeys()
            ->pluck('credential_id')
            ->map(fn (string $credentialId): string|false => base64_decode($credentialId, true))
            ->filter(fn (string|false $credentialId): bool => is_string($credentialId))
            ->values()
            ->all();

        if ($credentialIds === []) {
            return response()->json(['message' => 'Для этого пользователя ключ входа еще не добавлен.'], 404);
        }

        $webAuthn = $this->webAuthn($request);
        $options = $webAuthn->getGetArgs($credentialIds, 120, true, true, true, true, true, 'required');

        $request->session()->put('passkey.login.challenge', base64_encode($webAuthn->getChallenge()->getBinaryString()));
        $request->session()->put('passkey.login.user_id', $user->getKey());

        return response()->json($options);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $challenge = $this->pullChallenge($request, 'passkey.login.challenge');
        $userId = $request->session()->pull('passkey.login.user_id');
        $credentialId = base64_encode(base64_decode($data['id'], true) ?: '');

        if (! $challenge || ! $userId || $credentialId === '') {
            return response()->json(['message' => 'Сессия входа по ключу истекла.'], 419);
        }

        $passkey = UserPasskey::query()
            ->where('credential_id', $credentialId)
            ->where('user_id', $userId)
            ->with('user')
            ->first();

        if (! $passkey || ! $passkey->user || ! $passkey->user->is_active) {
            return response()->json(['message' => 'Ключ входа не найден.'], 404);
        }

        try {
            $webAuthn = $this->webAuthn($request);
            $webAuthn->processGet(
                base64_decode($data['clientDataJSON'], true) ?: '',
                base64_decode($data['authenticatorData'], true) ?: '',
                base64_decode($data['signature'], true) ?: '',
                $passkey->credential_public_key,
                $challenge,
                $passkey->signature_count,
                true,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Не удалось войти по ключу.'], 422);
        }

        $passkey->forceFill([
            'signature_count' => $webAuthn->getSignatureCounter() ?? $passkey->signature_count,
            'last_used_at' => now(),
        ])->save();

        Auth::login($passkey->user);
        $request->session()->regenerate();
        $passkey->user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'redirect' => redirect()->intended(route('bank-accounts.index'))->getTargetUrl(),
        ]);
    }

    public function destroy(UserPasskey $passkey): RedirectResponse
    {
        abort_unless($passkey->user_id === Auth::id(), 404);

        $passkey->delete();

        return redirect()
            ->route('passkeys.index')
            ->with('status', 'Ключ входа удален.');
    }

    private function webAuthn(Request $request): WebAuthn
    {
        return new WebAuthn(
            config('app.name', 'Legal Mamulov'),
            $request->getHost(),
            ['none'],
        );
    }

    private function pullChallenge(Request $request, string $key): ?ByteBuffer
    {
        $challenge = $request->session()->pull($key);
        if (! is_string($challenge)) {
            return null;
        }

        $binary = base64_decode($challenge, true);
        if (! is_string($binary)) {
            return null;
        }

        return new ByteBuffer($binary);
    }

    private function defaultPasskeyName(Request $request): string
    {
        $agent = trim((string) $request->userAgent());

        return $agent === ''
            ? 'Ключ входа'
            : mb_substr($agent, 0, 80);
    }
}
