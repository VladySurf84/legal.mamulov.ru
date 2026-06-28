@extends('layouts.app', [
    'title' => 'HH резюме',
    'titleDescription' => 'Отклики на вакансию, скачанные резюме и первичный разбор кандидатов.',
])

@section('page_actions')
    @if ($credential)
        <x-ui.button href="{{ route('hh.oauth.redirect') }}" size="md" variant="ghost">
            Переподключить HH
        </x-ui.button>
    @else
        <x-ui.button href="{{ route('hh.oauth.redirect') }}" size="md" variant="soft">
            Подключить HH
        </x-ui.button>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6">
        <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                        @if ($credential)
                            HH подключен
                        @else
                            HH не подключен
                        @endif
                    </div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Для синхронизации нужен OAuth от менеджера/работодателя, который видит отклики на вакансию.
                    </div>
                </div>

                <form method="post" action="{{ route('hh-resumes.sync') }}" class="grid gap-3 sm:grid-cols-[18rem_auto] sm:items-end">
                    @csrf
                    <x-ui.input
                        name="vacancy_id"
                        label="ID вакансии"
                        :value="$vacancyId"
                        placeholder="Например 123456789"
                    />
                    @if ($credential)
                        <x-ui.button type="submit" size="md" variant="soft">
                            Синхронизировать
                        </x-ui.button>
                    @else
                        <button type="button" disabled class="inline-flex items-center justify-center rounded-md bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 opacity-50 ring-1 ring-indigo-700/10">
                            Синхронизировать
                        </button>
                    @endif
                </form>
            </div>
        </section>

        @if ($vacancies->isNotEmpty())
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Последние вакансии</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($vacancies as $vacancy)
                        <a
                            href="{{ route('hh-resumes.index', ['vacancy_id' => $vacancy->hh_vacancy_id]) }}"
                            class="rounded-md px-2.5 py-1.5 text-sm font-medium ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5 {{ $vacancyId === $vacancy->hh_vacancy_id ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300' }}"
                        >
                            {{ $vacancy->name ?: 'Вакансия '.$vacancy->hh_vacancy_id }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="border-b border-gray-200 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Кандидаты</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="py-3.5 pr-3 pl-4 text-left font-semibold text-gray-900 dark:text-white sm:pl-6">Кандидат</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Отклик</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Оценка</th>
                            <th class="px-3 py-3.5 text-left font-semibold text-gray-900 dark:text-white">Разбор</th>
                            <th class="py-3.5 pr-4 pl-3 text-right font-semibold text-gray-900 dark:text-white sm:pr-6">Ссылки</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-800">
                        @forelse ($negotiations as $negotiation)
                            @php
                                $resumeRaw = is_string($negotiation->resume_raw ?? null)
                                    ? (json_decode($negotiation->resume_raw, true) ?: [])
                                    : (array) ($negotiation->resume_raw ?? []);
                                $candidateName = $negotiation->candidate_name ?: 'Кандидат без имени';
                                $candidateInitial = mb_strtoupper(mb_substr($candidateName, 0, 1));
                                $candidatePhoto = data_get($resumeRaw, 'photo.small')
                                    ?: data_get($resumeRaw, 'photo.100')
                                    ?: data_get($resumeRaw, 'photo.40')
                                    ?: data_get($resumeRaw, 'photo.medium')
                                    ?: data_get($resumeRaw, 'photo.500')
                                    ?: data_get($resumeRaw, 'browser_capture.resumeStructured.photo')
                                    ?: data_get($resumeRaw, 'browser_capture.browser_capture.resumeStructured.photo');
                                $responseUrl = $negotiation->alternate_url ?: $negotiation->resume_url;
                            @endphp
                            <tr
                                @if ($responseUrl)
                                    class="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5"
                                    ondblclick="window.open(@js($responseUrl), '_blank', 'noopener')"
                                @endif
                            >
                                <td class="py-3 pr-3 pl-4 align-top sm:pl-6">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $negotiation->candidate_name ?: 'Кандидат без имени' }}
                                    </div>
                                    <div class="mt-0.5 text-gray-500 dark:text-gray-400">{{ $negotiation->resume_title ?: 'Без заголовка' }}</div>
                                    @if ($negotiation->area_name)
                                        <div class="mt-0.5 text-xs text-gray-400">{{ $negotiation->area_name }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="text-gray-900 dark:text-white">{{ $negotiation->status_name ?: $negotiation->status_id ?: '—' }}</div>
                                    <div class="mt-0.5 font-mono text-xs text-gray-500">{{ $negotiation->responded_at ? \Illuminate\Support\Carbon::parse($negotiation->responded_at)->format('d.m.Y H:i') : '—' }}</div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span @class([
                                        'inline-flex min-w-14 justify-center rounded-md px-2 py-1 text-xs font-semibold ring-1',
                                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => (int) $negotiation->analysis_score >= 75,
                                        'bg-amber-50 text-amber-700 ring-amber-600/20' => (int) $negotiation->analysis_score >= 55 && (int) $negotiation->analysis_score < 75,
                                        'bg-gray-50 text-gray-700 ring-gray-600/20' => (int) $negotiation->analysis_score < 55,
                                    ])>
                                        {{ $negotiation->analysis_score ?? '—' }}
                                    </span>
                                </td>
                                <td class="max-w-xl px-3 py-3 align-top text-gray-600 dark:text-gray-300">
                                    {{ $negotiation->analysis_summary ?: 'Пока не анализировалось.' }}
                                </td>
                                <td class="py-3 pr-4 pl-3 text-right align-top sm:pr-6">
                                    <div class="flex justify-end gap-2">
                                        @if ($negotiation->alternate_url)
                                            <a class="text-sm font-medium text-indigo-600 hover:text-indigo-500" href="{{ $negotiation->alternate_url }}" target="_blank" rel="noopener">
                                                HH
                                            </a>
                                        @endif
                                        @if ($negotiation->pdf_path)
                                            <span class="text-sm text-gray-500" title="{{ $negotiation->pdf_path }}">PDF</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Синхронизированных откликов пока нет.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
