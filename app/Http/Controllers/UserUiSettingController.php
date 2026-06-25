<?php

namespace App\Http\Controllers;

use App\Models\UserUiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserUiSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:191'],
        ]);

        $setting = UserUiSetting::query()
            ->where('user_id', $request->user()->getKey())
            ->where('setting_key', $validated['key'])
            ->first();

        return response()->json([
            'settings' => $setting?->settings ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:191'],
            'settings' => ['required', 'array'],
        ]);

        $setting = UserUiSetting::query()->updateOrCreate([
            'user_id' => $request->user()->getKey(),
            'setting_key' => $validated['key'],
        ], [
            'settings' => $validated['settings'],
        ]);

        return response()->json([
            'settings' => $setting->settings ?? [],
        ]);
    }
}
