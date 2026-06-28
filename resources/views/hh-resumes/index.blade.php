@extends('layouts.app', [
    'title' => 'HH резюме',
    'titleDescription' => 'Отклики на вакансию, сохраненные резюме и первичный разбор кандидатов.',
])

@php
    $resumesCount = $negotiations->count();
    $capturedCount = $negotiations->filter(fn ($negotiation) => (bool) $negotiation->hh_browser_capture_id)->count();
    $highScoreCount = $negotiations->filter(fn ($negotiation) => (int) $negotiation->analysis_score >= 75)->count();
    $pdfCount = $negotiations->filter(fn ($negotiation) => filled($negotiation->pdf_path))->count();
    $scoreLabel = static function ($score): string {
        if ($score === null) {
            return 'Нет';
        }

        $score = (int) $score;

        return $score >= 75 ? 'Сильный' : ($score >= 55 ? 'Средний' : 'Слабый');
    };
@endphp

@section('title_meta')
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            {{ number_format($resumesCount, 0, ',', ' ') }} в списке
        </span>
        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">
            {{ number_format($highScoreCount, 0, ',', ' ') }} сильных
        </span>
        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20">
            {{ number_format($capturedCount, 0, ',', ' ') }} сохраненных
        </span>
        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            {{ number_format($pdfCount, 0, ',', ' ') }} PDF
        </span>
    </div>
@endsection

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button href="{{ route('hh-browser-captures.index', array_filter(['vacancy_id' => $vacancyId])) }}" size="md" variant="ghost">
            Архив откликов
        </x-ui.button>

        @if ($credential)
            <x-ui.button href="{{ route('hh.oauth.redirect') }}" size="md" variant="ghost">
                Переподключить HH
            </x-ui.button>
        @else
            <x-ui.button href="{{ route('hh.oauth.redirect') }}" size="md" variant="soft">
                Подключить HH
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/20">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-4 border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,auto)] lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($credential)
                        <span class="inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">
                            HH подключен
                        </span>
                    @else
                        <span class="inline-flex rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                            HH не подключен
                        </span>
                    @endif

                    @if ($vacancyId !== '')
                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">vacancy_id: {{ $vacancyId }}</span>
                    @endif
                </div>

                @if ($vacancies->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($vacancies as $vacancy)
                            <a
                                href="{{ route('hh-resumes.index', ['vacancy_id' => $vacancy->hh_vacancy_id]) }}"
                                class="rounded-md px-2.5 py-1.5 text-sm font-medium ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5 {{ $vacancyId === $vacancy->hh_vacancy_id ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300' }}"
                                wire:navigate
                            >
                                {{ $vacancy->name ?: 'Вакансия '.$vacancy->hh_vacancy_id }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <form method="post" action="{{ route('hh-resumes.sync') }}" class="grid gap-3 sm:grid-cols-[minmax(0,18rem)_auto] sm:items-end">
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
                    <button type="button" disabled class="inline-flex min-h-9 items-center justify-center rounded-md bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-400 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-500 dark:ring-white/10">
                        Синхронизировать
                    </button>
                @endif
            </form>
        </div>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Кандидат</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Отклик</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Оценка</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Разбор</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Файлы</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Действия</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($negotiations as $negotiation)
            @php
                $candidateName = $negotiation->display_candidate_name;
                $candidateInitial = mb_strtoupper(mb_substr($candidateName, 0, 1));
                $candidatePhoto = $negotiation->display_candidate_photo;
                $detailUrl = $negotiation->hh_browser_capture_id ? route('hh-browser-captures.show', $negotiation->hh_browser_capture_id) : null;
                $score = $negotiation->analysis_score;
            @endphp

            <tr
                @class([
                    'align-top hover:bg-gray-50 dark:hover:bg-white/5',
                    'cursor-pointer' => $detailUrl,
                ])
                @if ($detailUrl)
                    data-href="{{ $detailUrl }}"
                    ondblclick="window.location.href = this.dataset.href"
                @endif
            >
                <x-ui.sticky-table-td first :nowrap="false" class="min-w-72">
                    <div class="flex items-start gap-3">
                        @if ($candidatePhoto)
                            <img src="{{ $candidatePhoto }}" alt="" class="size-10 shrink-0 rounded-full object-cover outline -outline-offset-1 outline-gray-200 dark:outline-white/10">
                        @else
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
                                {{ $candidateInitial }}
                            </span>
                        @endif

                        <div class="min-w-0">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $candidateName }}</div>
                            <div class="mt-0.5 max-w-md truncate text-gray-500 dark:text-gray-400">{{ $negotiation->resume_title ?: 'Без заголовка' }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-400">
                                @if ($negotiation->area_name)
                                    <span>{{ $negotiation->area_name }}</span>
                                @endif
                                <span class="font-mono">vacancyId: {{ $negotiation->hh_vacancy_id }}</span>
                                <span class="font-mono">resumeId: {{ $negotiation->resume_id }}</span>
                            </div>
                        </div>
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td nowrap>
                    <div class="font-medium text-gray-900 dark:text-white">{{ $negotiation->status_name ?: $negotiation->status_id ?: '—' }}</div>
                    <div class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                        {{ $negotiation->responded_at ? \Illuminate\Support\Carbon::parse($negotiation->responded_at)->format('d.m.Y H:i') : '—' }}
                    </div>
                    @if ($negotiation->vacancy_name)
                        <div class="mt-1 max-w-56 truncate text-xs text-gray-400">{{ $negotiation->vacancy_name }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" nowrap>
                    <div @class([
                        'ml-auto inline-flex min-w-16 justify-center rounded-md px-2 py-1 text-xs font-semibold ring-1',
                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' => (int) $score >= 75,
                        'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20' => (int) $score >= 55 && (int) $score < 75,
                        'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => $score === null || (int) $score < 55,
                    ])>
                        {{ $score ?? '—' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">{{ $scoreLabel($score) }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-80 max-w-2xl text-gray-600 dark:text-gray-300">
                    {{ $negotiation->analysis_summary ?: 'Пока не анализировалось.' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td nowrap>
                    <div class="flex flex-wrap gap-2">
                        @if ($detailUrl)
                            <a class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200" href="{{ $detailUrl }}" onclick="event.stopPropagation()" wire:navigate>
                                Отклик
                            </a>
                        @endif
                        @if ($negotiation->alternate_url)
                            <a class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200" href="{{ $negotiation->alternate_url }}" target="_blank" rel="noopener" onclick="event.stopPropagation()">
                                HH
                            </a>
                        @endif
                        @if ($negotiation->pdf_path)
                            <span class="text-gray-500 dark:text-gray-400" title="{{ $negotiation->pdf_path }}">PDF</span>
                        @endif
                        @unless ($detailUrl || $negotiation->alternate_url || $negotiation->pdf_path)
                            <span class="text-gray-400">—</span>
                        @endunless
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last align="right" nowrap>
                    <details class="group relative inline-block text-left" onclick="event.stopPropagation()" ondblclick="event.stopPropagation()">
                        <summary class="flex size-8 cursor-pointer list-none items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 dark:hover:bg-white/10 dark:hover:text-white [&::-webkit-details-marker]:hidden" aria-label="Действия">
                            ⋮
                        </summary>
                        <div class="absolute right-0 z-30 mt-1 w-44 rounded-md border border-gray-200 bg-white p-1 text-left shadow-lg ring-1 ring-black/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
                            @if ($detailUrl)
                                <a href="{{ $detailUrl }}" class="block rounded px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10" wire:navigate>
                                    Открыть отклик
                                </a>
                            @endif
                            <form method="post" action="{{ route('hh-resumes.destroy', $negotiation->hh_negotiation_id) }}" onsubmit="return confirm('Удалить HH резюме из списка?')">
                                @csrf
                                @method('delete')
                                <button type="submit" class="block w-full rounded px-3 py-1.5 text-left text-sm text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                    Удалить
                                </button>
                            </form>
                        </div>
                    </details>
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Синхронизированных откликов пока нет.
                </td>
            </tr>
        @endforelse

        <x-slot:stickySummary>
            <tr>
                <x-ui.sticky-table-summary-label first :columns="1">
                    Всего: {{ number_format($resumesCount, 0, ',', ' ') }}
                </x-ui.sticky-table-summary-label>
                <x-ui.sticky-table-td summary nowrap class="text-sm text-gray-600 dark:text-gray-300">
                    Сохраненных: {{ number_format($capturedCount, 0, ',', ' ') }}
                </x-ui.sticky-table-td>
                <x-ui.sticky-table-td summary align="right" nowrap class="text-sm font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                    {{ number_format($highScoreCount, 0, ',', ' ') }}
                </x-ui.sticky-table-td>
                <x-ui.sticky-table-td summary :nowrap="false" class="text-sm text-gray-600 dark:text-gray-300">
                    Сильных кандидатов по текущей выборке
                </x-ui.sticky-table-td>
                <x-ui.sticky-table-td summary nowrap class="text-sm text-gray-600 dark:text-gray-300">
                    PDF: {{ number_format($pdfCount, 0, ',', ' ') }}
                </x-ui.sticky-table-td>
                <x-ui.sticky-table-td summary last align="right" nowrap />
            </tr>
        </x-slot:stickySummary>
    </x-ui.sticky-table>
@endsection
