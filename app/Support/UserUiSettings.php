<?php

namespace App\Support;

use App\Models\UserUiSetting;
use Illuminate\Http\Request;

class UserUiSettings
{
    public static function stickyTableKey(Request $request, string $bodyId = 'main'): string
    {
        return implode(':', array_filter([
            'sticky-table',
            $request->route()?->getName() ?: $request->path(),
            $bodyId,
        ]));
    }

    public static function paginationRows(Request $request, string $bodyId, int $default): int
    {
        if ($request->query->has('per_page')) {
            return self::normalizePaginationRows($request->query('per_page'), $default);
        }

        $settings = UserUiSetting::query()
            ->where('user_id', $request->user()?->getKey())
            ->where('setting_key', self::stickyTableKey($request, $bodyId))
            ->value('settings');

        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        return self::normalizePaginationRows($settings['paginationRows'] ?? null, $default);
    }

    private static function normalizePaginationRows(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(0, min(1000, (int) $value));
    }
}
