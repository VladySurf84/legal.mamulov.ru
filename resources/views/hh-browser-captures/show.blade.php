@extends('layouts.app', [
    'title' => $capture->candidate_name ?: 'HH отклик',
    'titleDescription' => ($capture->vacancy_title ?: 'Сохраненный отклик с hh.ru'),
])

@section('page_actions')
    <x-ui.button href="{{ route('hh-browser-captures.index', array_filter(['vacancy_id' => $capture->hh_vacancy_id])) }}" size="md" variant="ghost">
        К списку
    </x-ui.button>
    @if ($capture->original_url ?: $capture->page_url)
        <x-ui.button href="{{ $capture->original_url ?: $capture->page_url }}" size="md" variant="soft" target="_blank" rel="noopener">
            Оригинал HH
        </x-ui.button>
    @endif
@endsection

@section('content')
    @php
        $skills = (array) data_get($structured, 'skills', []);
        $sections = (array) data_get($structured, 'sections', []);
        $experience = (array) data_get($structured, 'experience', []);
        $stringify = function (mixed $value): string {
            if (! is_array($value)) {
                return (string) $value;
            }

            return collect($value)
                ->map(function (mixed $item): string {
                    if (! is_array($item)) {
                        return (string) $item;
                    }

                    return collect($item)
                        ->filter(fn (mixed $nested): bool => filled($nested))
                        ->map(function (mixed $nested, string|int $key): string {
                            $text = is_array($nested)
                                ? json_encode($nested, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                : (string) $nested;

                            return $key.': '.$text;
                        })
                        ->implode("\n");
                })
                ->filter()
                ->implode("\n\n");
        };
        $facts = [
            'resumeId' => $capture->resume_id,
            'Вакансия' => $capture->vacancy_title ?: $capture->hh_vacancy_id,
            'Город' => $capture->candidate_location,
            'Возраст' => $capture->candidate_age,
            'Зарплата' => data_get($structured, 'salary'),
            'Занятость' => data_get($structured, 'employment'),
            'График' => data_get($structured, 'schedule'),
            'Опыт' => data_get($structured, 'experienceTotal'),
            'Специализация' => data_get($structured, 'specialization'),
            'Сохранено' => $capture->captured_at ? \Illuminate\Support\Carbon::parse($capture->captured_at)->format('d.m.Y H:i') : null,
        ];
    @endphp

    <div class="space-y-6">
        <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
            <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ data_get($structured, 'title') ?: $capture->page_title ?: 'Резюме без заголовка' }}</h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($facts as $label => $value)
                            @if ($value)
                                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-white/5">
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
                <div class="rounded-md bg-gray-50 p-3 dark:bg-white/5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Технически</div>
                    <div class="mt-2 space-y-1 font-mono text-xs text-gray-700 dark:text-gray-300">
                        <div>ID: {{ $capture->hh_browser_capture_id }}</div>
                        @if ($capture->original_url)
                            <div class="break-all">original: {{ $capture->original_url }}</div>
                        @endif
                        <div>source: {{ $capture->source }}</div>
                        <div>текст: {{ number_format(mb_strlen((string) $capture->raw_text), 0, ',', ' ') }} симв.</div>
                        <div>секций: {{ count($sections) }}</div>
                    </div>
                </div>
            </div>
        </section>

        @if ($skills !== [])
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Навыки</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($skills as $skill)
                        <span class="rounded-md bg-emerald-50 px-2.5 py-1.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $skill }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ([
                'Опыт работы' => $experience,
                'Образование' => data_get($structured, 'education'),
                'Языки' => data_get($structured, 'languages'),
                'О себе' => data_get($structured, 'about'),
                'Гражданство' => data_get($structured, 'citizenship'),
                'Разрешение на работу' => data_get($structured, 'workPermit'),
            ] as $heading => $value)
                @if (is_array($value) ? $value !== [] : filled($value))
                    <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $heading }}</h2>
                        <div class="mt-3 whitespace-pre-wrap text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $stringify($value) }}</div>
                    </section>
                @endif
            @endforeach
        </div>

        @if ($sections !== [])
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Секции резюме</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($sections as $section)
                        <article class="rounded-md border border-gray-200 p-3 dark:border-white/10">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ data_get($section, 'heading') ?: data_get($section, 'key') ?: 'Секция' }}</h3>
                            <div class="mt-2 whitespace-pre-wrap text-sm leading-6 text-gray-700 dark:text-gray-300">{{ data_get($section, 'text') }}</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Полный текст страницы</h2>
            <pre class="mt-3 max-h-[42rem] overflow-auto whitespace-pre-wrap rounded-md bg-gray-950 p-4 text-xs leading-5 text-gray-100">{{ $capture->raw_text ?: 'Нет текста.' }}</pre>
        </section>

        <details class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">JSON payload</summary>
            <pre class="mt-3 max-h-[34rem] overflow-auto rounded-md bg-gray-950 p-4 text-xs leading-5 text-gray-100">{{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
        </details>
    </div>
@endsection
