@extends('layouts.app', [
    'title' => 'HH резюме',
    'titleDescription' => 'Отклики на вакансию, сохраненные резюме и первичный разбор кандидатов.',
])

@php
    $resumesCount = (int) ($summary['count'] ?? $negotiations->total());
    $pageResumesCount = $negotiations->count();
    $capturedCount = (int) ($summary['captured_count'] ?? 0);
    $highScoreCount = (int) ($summary['high_score_count'] ?? 0);
    $pdfCount = (int) ($summary['pdf_count'] ?? 0);
    $firstItem = $negotiations->firstItem() ?? 0;
    $lastItem = $negotiations->lastItem() ?? 0;
    $batchStatus = $latestAnalysisBatch->status ?? null;
    $batchStatusLabel = match ($batchStatus) {
        'validating' => 'Проверяется',
        'in_progress' => 'В обработке',
        'finalizing' => 'Завершается',
        'completed' => 'Готово',
        'failed' => 'Ошибка',
        'expired' => 'Истекло',
        'cancelled' => 'Отменено',
        default => $batchStatus,
    };
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
        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            показано {{ number_format($pageResumesCount, 0, ',', ' ') }}
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
        <form method="post" action="{{ route('hh-resumes.analyze-all') }}">
            @csrf
            @if ($vacancyId !== '')
                <input type="hidden" name="vacancy_id" value="{{ $vacancyId }}">
            @endif
            <x-ui.button type="submit" size="md" variant="soft">
                Оценить все
            </x-ui.button>
        </form>

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

                    @if ($latestAnalysisBatch)
                        <span class="inline-flex rounded-md bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20">
                            AI batch #{{ $latestAnalysisBatch->hh_resume_analysis_batch_id }}: {{ $batchStatusLabel }}
                            · {{ number_format((int) $latestAnalysisBatch->processed_count, 0, ',', ' ') }}/{{ number_format((int) $latestAnalysisBatch->total_count, 0, ',', ' ') }}
                        </span>
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

    <div class="mb-3 text-sm text-gray-600 dark:text-gray-300">
        <div>
            @if ($resumesCount > 0)
                Показано {{ number_format($firstItem, 0, ',', ' ') }}-{{ number_format($lastItem, 0, ',', ' ') }} из {{ number_format($resumesCount, 0, ',', ' ') }}
            @else
                Откликов нет
            @endif
        </div>
    </div>

    <x-ui.sticky-table
        body-id="hh-resumes-rows"
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
                <x-ui.sticky-table-th align="right">Оценка Codex</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Разбор</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Файлы</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @include('hh-resumes.partials.rows', [
            'negotiations' => $negotiations,
            'scoreLabel' => $scoreLabel,
        ])

        @include('hh-resumes.partials.loader-row', [
            'nextPage' => $nextPage,
            'tableColspan' => 6,
        ])

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
                <x-ui.sticky-table-td summary align="right" nowrap class="text-sm font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                    Codex
                </x-ui.sticky-table-td>
                <x-ui.sticky-table-td summary :nowrap="false" class="text-sm text-gray-600 dark:text-gray-300">
                    Сильных кандидатов по текущей выборке
                </x-ui.sticky-table-td>
                <x-ui.sticky-table-td summary nowrap class="text-sm text-gray-600 dark:text-gray-300">
                    PDF: {{ number_format($pdfCount, 0, ',', ' ') }}
                </x-ui.sticky-table-td>
            </tr>
        </x-slot:stickySummary>
    </x-ui.sticky-table>

    @if ($negotiations->hasPages())
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600 dark:text-gray-300">
            <div>
                Страница {{ number_format($negotiations->currentPage(), 0, ',', ' ') }} из {{ number_format($negotiations->lastPage(), 0, ',', ' ') }}
            </div>

            <div class="flex flex-wrap items-center gap-1">
                @if ($negotiations->onFirstPage())
                    <span class="inline-flex min-h-8 items-center rounded-md px-2.5 text-gray-400 ring-1 ring-gray-200 dark:ring-white/10">Назад</span>
                @else
                    <a href="{{ $negotiations->previousPageUrl() }}" class="inline-flex min-h-8 items-center rounded-md px-2.5 font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5" wire:navigate>Назад</a>
                @endif

                @php
                    $startPage = max(1, $negotiations->currentPage() - 2);
                    $endPage = min($negotiations->lastPage(), $negotiations->currentPage() + 2);
                @endphp

                @if ($startPage > 1)
                    <a href="{{ $negotiations->url(1) }}" class="inline-flex size-8 items-center justify-center rounded-md font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5" wire:navigate>1</a>
                    @if ($startPage > 2)
                        <span class="inline-flex size-8 items-center justify-center text-gray-400">…</span>
                    @endif
                @endif

                @for ($page = $startPage; $page <= $endPage; $page++)
                    @if ($page === $negotiations->currentPage())
                        <span class="inline-flex size-8 items-center justify-center rounded-md bg-gray-900 text-sm font-semibold text-white dark:bg-white dark:text-gray-900">{{ $page }}</span>
                    @else
                        <a href="{{ $negotiations->url($page) }}" class="inline-flex size-8 items-center justify-center rounded-md font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5" wire:navigate>{{ $page }}</a>
                    @endif
                @endfor

                @if ($endPage < $negotiations->lastPage())
                    @if ($endPage < $negotiations->lastPage() - 1)
                        <span class="inline-flex size-8 items-center justify-center text-gray-400">…</span>
                    @endif
                    <a href="{{ $negotiations->url($negotiations->lastPage()) }}" class="inline-flex size-8 items-center justify-center rounded-md font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5" wire:navigate>{{ $negotiations->lastPage() }}</a>
                @endif

                @if ($negotiations->hasMorePages())
                    <a href="{{ $negotiations->nextPageUrl() }}" class="inline-flex min-h-8 items-center rounded-md px-2.5 font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5" wire:navigate>Вперед</a>
                @else
                    <span class="inline-flex min-h-8 items-center rounded-md px-2.5 text-gray-400 ring-1 ring-gray-200 dark:ring-white/10">Вперед</span>
                @endif
            </div>
        </div>
    @endif

    <x-ui.context-menu trigger-selector="[data-hh-resume-context-row]">
        <x-slot:menu>
            <x-ui.context-menu-item data-hh-resume-open-detail>
                Открыть отклик
            </x-ui.context-menu-item>
            <x-ui.context-menu-item data-hh-resume-open-hh>
                Открыть HH
            </x-ui.context-menu-item>
            <x-ui.context-menu-item danger data-hh-resume-delete>
                Удалить
            </x-ui.context-menu-item>
        </x-slot:menu>
    </x-ui.context-menu>

    <form method="post" class="hidden" data-hh-resume-delete-form>
        @csrf
        @method('delete')
    </form>

    @once
        <script>
            (() => {
                const initHhResumeContextMenu = () => {
                    const menu = document.querySelector('[data-ui-context-menu-trigger-selector="[data-hh-resume-context-row]"]');
                    const deleteForm = document.querySelector('[data-hh-resume-delete-form]');

                    if (!menu || menu.dataset.hhResumeMenuReady === 'true') {
                        return;
                    }

                    menu.dataset.hhResumeMenuReady = 'true';

                    document.addEventListener('contextmenu', (event) => {
                        const row = event.target.closest('[data-hh-resume-context-row]');

                        if (!row) {
                            return;
                        }

                        menu.dataset.row = JSON.stringify(row.dataset);
                        menu.querySelector('[data-hh-resume-open-detail]')?.toggleAttribute('disabled', ! row.dataset.hhResumeDetailUrl);
                        menu.querySelector('[data-hh-resume-open-hh]')?.toggleAttribute('disabled', ! row.dataset.hhResumeHhUrl);
                        menu.querySelector('[data-hh-resume-delete]')?.toggleAttribute('disabled', ! row.dataset.hhResumeDeleteUrl);
                    });

                    menu.querySelector('[data-hh-resume-open-detail]')?.addEventListener('click', () => {
                        const data = JSON.parse(menu.dataset.row || '{}');

                        if (data.hhResumeDetailUrl) {
                            window.location.href = data.hhResumeDetailUrl;
                        }
                    });

                    menu.querySelector('[data-hh-resume-open-hh]')?.addEventListener('click', () => {
                        const data = JSON.parse(menu.dataset.row || '{}');

                        if (data.hhResumeHhUrl) {
                            window.open(data.hhResumeHhUrl, '_blank', 'noopener');
                        }
                    });

                    menu.querySelector('[data-hh-resume-delete]')?.addEventListener('click', () => {
                        const data = JSON.parse(menu.dataset.row || '{}');

                        if (!data.hhResumeDeleteUrl || !deleteForm || !window.confirm('Удалить HH резюме из списка?')) {
                            return;
                        }

                        deleteForm.action = data.hhResumeDeleteUrl;
                        deleteForm.submit();
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initHhResumeContextMenu);
                } else {
                    initHhResumeContextMenu();
                }

                document.addEventListener('livewire:navigated', initHhResumeContextMenu);
            })();
        </script>
    @endonce
@endsection
