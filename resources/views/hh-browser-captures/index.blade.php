@extends('layouts.app', [
    'title' => 'HH browser captures',
    'titleDescription' => 'Резюме и отклики, сохраненные расширением с открытых страниц hh.ru.',
])

@section('content')
    <div class="space-y-6">
        <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
            <form method="get" action="{{ route('hh-browser-captures.index') }}" class="grid gap-3 lg:grid-cols-[18rem_1fr_auto] lg:items-end">
                <x-ui.input
                    name="vacancy_id"
                    label="ID вакансии"
                    :value="$vacancyId"
                    placeholder="134293071"
                />
                <x-ui.input
                    name="q"
                    label="Поиск"
                    :value="$search"
                    placeholder="Имя, должность, навык, resumeId"
                />
                <div class="flex gap-2">
                    <x-ui.button type="submit" size="md" variant="soft">Найти</x-ui.button>
                    <x-ui.button href="{{ route('hh-browser-captures.index') }}" size="md" variant="ghost">Сбросить</x-ui.button>
                </div>
            </form>
        </section>

        @if ($vacancies->isNotEmpty())
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Вакансии из сохраненных откликов</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($vacancies as $vacancy)
                        <a
                            href="{{ route('hh-browser-captures.index', ['vacancy_id' => $vacancy->hh_vacancy_id]) }}"
                            class="rounded-md px-2.5 py-1.5 text-sm font-medium ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5 {{ $vacancyId === $vacancy->hh_vacancy_id ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300' }}"
                        >
                            {{ $vacancy->vacancy_title ?: 'Вакансия '.$vacancy->hh_vacancy_id }}
                            <span class="ml-1 text-xs text-gray-400">{{ $vacancy->captures_count }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="border-b border-gray-200 px-4 py-4 dark:border-white/10 sm:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Сохраненные резюме</h2>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Двойной клик по строке открывает отклик</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="py-3.5 pr-3 pl-4 text-left font-semibold text-gray-900 dark:text-white sm:pl-6">Кандидат</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Вакансия</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Структура</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Текст</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Сохранено</th>
                            <th class="py-3.5 pr-4 pl-3 text-right font-semibold text-gray-900 dark:text-white sm:pr-6">Ссылки</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-800">
                        @forelse ($captures as $capture)
                            @php
                                $structured = json_decode($capture->resume_structured ?? '{}', true) ?: [];
                                $title = data_get($structured, 'title') ?: $capture->page_title;
                                $skillsCount = count((array) data_get($structured, 'skills', []));
                                $sectionsCount = count((array) data_get($structured, 'sections', []));
                            @endphp
                            <tr
                                data-href="{{ route('hh-browser-captures.show', $capture->hh_browser_capture_id) }}"
                                class="cursor-pointer hover:bg-emerald-50/70 dark:hover:bg-emerald-500/10"
                                title="Двойной клик: открыть отклик"
                            >
                                <td class="py-3 pr-3 pl-4 align-top sm:pl-6">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $capture->candidate_name ?: 'Кандидат без имени' }}</div>
                                    <div class="mt-0.5 max-w-md truncate text-gray-500 dark:text-gray-400">{{ $title ?: 'Без заголовка' }}</div>
                                    <div class="mt-0.5 font-mono text-xs text-gray-400">resumeId: {{ $capture->resume_id ?: '—' }}</div>
                                </td>
                                <td class="max-w-sm px-3 py-3 align-top">
                                    <div class="truncate text-gray-900 dark:text-white">{{ $capture->vacancy_title ?: '—' }}</div>
                                    <div class="mt-0.5 font-mono text-xs text-gray-500">{{ $capture->hh_vacancy_id ?: '—' }}</div>
                                </td>
                                <td class="px-3 py-3 align-top text-gray-600 dark:text-gray-300">
                                    <div>{{ $skillsCount }} навыков</div>
                                    <div class="mt-0.5 text-xs text-gray-400">{{ $sectionsCount }} секций</div>
                                </td>
                                <td class="px-3 py-3 align-top text-gray-600 dark:text-gray-300">
                                    {{ number_format(mb_strlen((string) $capture->raw_text), 0, ',', ' ') }} симв.
                                </td>
                                <td class="px-3 py-3 align-top font-mono text-xs text-gray-500">
                                    {{ $capture->captured_at ? \Illuminate\Support\Carbon::parse($capture->captured_at)->format('d.m.Y H:i') : '—' }}
                                </td>
                                <td class="py-3 pr-4 pl-3 text-right align-top sm:pr-6">
                                    @if ($capture->original_url ?: $capture->page_url)
                                        <a class="font-medium text-indigo-600 hover:text-indigo-500" href="{{ $capture->original_url ?: $capture->page_url }}" target="_blank" rel="noopener" onclick="event.stopPropagation()">HH</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Сохраненных резюме пока нет.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10 sm:px-6">
                {{ $captures->links() }}
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('tr[data-href]').forEach((row) => {
                row.addEventListener('dblclick', () => {
                    window.location.href = row.dataset.href;
                });
            });
        });
    </script>
@endsection