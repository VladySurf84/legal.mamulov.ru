<?php

namespace App\Services\Hh;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HhResumeAnalyzer
{
    /**
     * @return array{score: int, summary: string, flags: array<int, array{label: string, weight: int, matched: bool}>}
     */
    public function analyze(array $resume): array
    {
        $text = Str::lower($this->resumeText($resume));
        $rules = [
            ['label' => 'PHP', 'weight' => 12, 'patterns' => ['php']],
            ['label' => 'Laravel', 'weight' => 20, 'patterns' => ['laravel']],
            ['label' => 'PostgreSQL', 'weight' => 14, 'patterns' => ['postgresql', 'postgres', 'pgsql']],
            ['label' => 'Backend/API', 'weight' => 12, 'patterns' => ['backend', 'back-end', 'api', 'rest']],
            ['label' => 'Интеграции', 'weight' => 10, 'patterns' => ['интеграц', 'integration', 'api интеграц']],
            ['label' => 'ERP/учет/бухгалтерия', 'weight' => 10, 'patterns' => ['erp', 'бухгалтер', 'учет', 'учёт', 'финанс', 'склад']],
            ['label' => 'Git/Linux/Docker', 'weight' => 8, 'patterns' => ['git', 'linux', 'docker']],
            ['label' => 'Только WordPress/CMS', 'weight' => -10, 'patterns' => ['wordpress', 'joomla', 'bitrix', 'битрикс']],
            ['label' => 'Только frontend', 'weight' => -8, 'patterns' => ['react', 'vue', 'frontend', 'front-end']],
        ];

        $score = 20;
        $flags = [];

        foreach ($rules as $rule) {
            $matched = collect($rule['patterns'])
                ->contains(fn (string $pattern): bool => str_contains($text, Str::lower($pattern)));

            if ($matched) {
                $score += (int) $rule['weight'];
            }

            $flags[] = [
                'label' => $rule['label'],
                'weight' => (int) $rule['weight'],
                'matched' => $matched,
            ];
        }

        $experienceMonths = (int) Arr::get($resume, 'total_experience.months', 0);

        if ($experienceMonths >= 60) {
            $score += 12;
            $flags[] = ['label' => 'Опыт 5+ лет', 'weight' => 12, 'matched' => true];
        } elseif ($experienceMonths >= 36) {
            $score += 8;
            $flags[] = ['label' => 'Опыт 3+ года', 'weight' => 8, 'matched' => true];
        } elseif ($experienceMonths > 0 && $experienceMonths < 24) {
            $score -= 6;
            $flags[] = ['label' => 'Мало опыта', 'weight' => -6, 'matched' => true];
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'summary' => $this->summary($score, $flags, $experienceMonths),
            'flags' => $flags,
        ];
    }

    private function resumeText(array $resume): string
    {
        $parts = [
            Arr::get($resume, 'title'),
            Arr::get($resume, 'skills'),
            implode(' ', array_filter((array) Arr::get($resume, 'skill_set', []))),
        ];

        foreach ((array) Arr::get($resume, 'experience', []) as $experience) {
            if (! is_array($experience)) {
                continue;
            }

            $parts[] = Arr::get($experience, 'company');
            $parts[] = Arr::get($experience, 'position');
            $parts[] = Arr::get($experience, 'description');
        }

        foreach ((array) Arr::get($resume, 'education.primary', []) as $education) {
            if (is_array($education)) {
                $parts[] = implode(' ', array_filter(Arr::only($education, ['name', 'organization', 'result'])));
            }
        }

        return implode("\n", array_filter(array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $parts)));
    }

    private function summary(int $score, array $flags, int $experienceMonths): string
    {
        $matchedPositive = collect($flags)
            ->filter(fn (array $flag): bool => $flag['matched'] && $flag['weight'] > 0)
            ->pluck('label')
            ->take(4)
            ->implode(', ');

        $matchedNegative = collect($flags)
            ->filter(fn (array $flag): bool => $flag['matched'] && $flag['weight'] < 0)
            ->pluck('label')
            ->implode(', ');

        $years = $experienceMonths > 0
            ? number_format($experienceMonths / 12, 1, ',', ' ').' г. опыта'
            : 'опыт не указан';

        $level = match (true) {
            $score >= 75 => 'Сильный кандидат',
            $score >= 55 => 'Стоит посмотреть',
            $score >= 35 => 'Сомнительное соответствие',
            default => 'Слабое соответствие',
        };

        $summary = "{$level}: {$score}/100, {$years}.";

        if ($matchedPositive !== '') {
            $summary .= " Совпадения: {$matchedPositive}.";
        }

        if ($matchedNegative !== '') {
            $summary .= " Риски: {$matchedNegative}.";
        }

        return $summary;
    }
}
