@php
    if (! isset($scoreLabel)) {
        $scoreLabel = static function ($score): string {
            if ($score === null) {
                return 'Нет';
            }

            $score = (int) $score;

            return $score >= 75 ? 'Сильный' : ($score >= 55 ? 'Средний' : 'Слабый');
        };
    }

    $canManageHhResumes = $canManageHhResumes ?? false;
    $canViewHhBrowserCaptures = $canViewHhBrowserCaptures ?? true;
@endphp

@forelse ($negotiations as $negotiation)
    @php
        $candidateName = $negotiation->display_candidate_name;
        $candidateInitial = mb_strtoupper(mb_substr($candidateName, 0, 1));
        $candidatePhoto = $negotiation->display_candidate_photo;
        $detailUrl = $canViewHhBrowserCaptures && $negotiation->hh_browser_capture_id ? route('hh-browser-captures.show', $negotiation->hh_browser_capture_id) : null;
        $score = $negotiation->analysis_score;
        $codexScore = $negotiation->codex_analysis_score;
        $summaryText = $negotiation->codex_analysis_summary ?: $negotiation->analysis_summary;
        $coverLetter = $negotiation->display_cover_letter;
    @endphp

    <tr
        @class([
            'align-top hover:bg-gray-50 dark:hover:bg-white/5',
            'cursor-pointer' => $detailUrl,
        ])
        @if ($detailUrl)
            data-href="{{ $detailUrl }}"
            data-hh-resume-detail-url="{{ $detailUrl }}"
            ondblclick="window.location.href = this.dataset.href"
        @endif
        data-hh-resume-context-row
        @if ($canManageHhResumes)
            data-hh-resume-delete-url="{{ route('hh-resumes.destroy', $negotiation->hh_negotiation_id) }}"
        @endif
        data-hh-resume-hh-url="{{ $negotiation->alternate_url }}"
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
                        <span class="font-mono">resumeId: {{ $negotiation->display_resume_id }}</span>
                    </div>
                </div>
            </div>
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false" class="min-w-96 max-w-xl text-sm">
            @if ($coverLetter)
                <div data-hh-cover-letter class="line-clamp-5 whitespace-pre-line text-gray-700 dark:text-gray-200">{{ $coverLetter }}</div>
                <button
                    type="button"
                    class="mt-1 hidden text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200"
                    data-hh-cover-letter-toggle
                    data-collapsed-label="Полностью"
                    data-expanded-label="Сократить"
                    onclick="event.stopPropagation()"
                >
                    Полностью
                </button>
            @else
                <div class="text-gray-400">Нет сопроводительного письма</div>
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

        <x-ui.sticky-table-td align="right" nowrap>
            <div @class([
                'ml-auto inline-flex min-w-16 justify-center rounded-md px-2 py-1 text-xs font-semibold ring-1',
                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' => (int) $codexScore >= 75,
                'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20' => (int) $codexScore >= 55 && (int) $codexScore < 75,
                'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => $codexScore === null || (int) $codexScore < 55,
            ])>
                {{ $codexScore ?? '—' }}
            </div>
            <div class="mt-1 text-xs text-gray-400">{{ $scoreLabel($codexScore) }}</div>
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false" class="min-w-80 max-w-2xl text-gray-600 dark:text-gray-300">
            {{ $summaryText ?: 'Пока не анализировалось.' }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td last nowrap>
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
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
            Синхронизированных откликов пока нет.
        </td>
    </tr>
@endforelse
