@props([
    'align' => 'left',
    'hiddenUntil' => null,
    'first' => false,
    'last' => false,
    'padding' => null,
    'autoFilter' => null,
    'filterForm' => null,
    'filterLabel' => null,
])

@php
    $alignClass = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';

    $visibilityClass = [
        'sm' => 'hidden sm:table-cell',
        'md' => 'hidden md:table-cell',
        'lg' => 'hidden lg:table-cell',
        'xl' => 'hidden xl:table-cell',
    ][$hiddenUntil] ?? '';

    $paddingClass = $padding ?: match (true) {
        $first => 'py-2.5 pr-3 pl-4 sm:pl-6 lg:pl-8',
        $last => 'py-2.5 pr-4 pl-3 sm:pr-6 lg:pr-8',
        default => 'px-3 py-2.5',
    };
@endphp

<th
    scope="col"
    {{ $attributes->class(trim("sticky top-0 z-10 border-b border-gray-300 bg-white/75 text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75 dark:text-white {$paddingClass} {$alignClass} {$visibilityClass}")) }}
>
    <span class="inline-flex items-center gap-2">
        {{ $slot }}
        @if ($autoFilter && $filterForm)
            @php($filterId = 'column-filter-'.Illuminate\Support\Str::uuid())
            <button
                type="button"
                popovertarget="{{ $filterId }}"
                aria-label="Фильтр: {{ $filterLabel ?? trim(strip_tags((string) $slot)) }}"
                @class(['rounded p-1 hover:bg-gray-100 dark:hover:bg-white/10', 'text-indigo-600 dark:text-indigo-400' => filled(request($autoFilter))])
                data-ui-column-filter-button
            >
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4h14v2l-5.5 6v4l-3 1v-5L3 6V4Z"/></svg>
            </button>
            <div id="{{ $filterId }}" popover="auto" class="m-0 w-72 rounded-lg border border-gray-200 bg-white p-3 text-sm font-normal text-gray-900 shadow-lg dark:border-white/15 dark:bg-gray-900 dark:text-white" data-ui-column-filter-popover>
                <label for="{{ $filterId }}-input" class="mb-2 block font-medium">{{ $filterLabel ?? trim(strip_tags((string) $slot)) }}</label>
                <x-ui.input
                    id="{{ $filterId }}-input"
                    type="search"
                    maxlength="200"
                    :value="request($autoFilter, '')"
                    placeholder="Поиск…"
                    autocomplete="off"
                    :data-ui-column-filter-name="$autoFilter"
                    :data-ui-column-filter-form="$filterForm"
                />
            </div>
        @endif
    </span>
</th>

@once
    <script>
        (() => {
            const anchors = new Map();
            const position = (panel) => {
                const button = anchors.get(panel.id) ?? document.querySelector(`[popovertarget="${panel.id}"]`);
                if (!button) return;
                const rect = button.getBoundingClientRect();
                panel.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 8))}px`;
                panel.style.top = `${Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - panel.offsetHeight - 8))}px`;
            };
            // Sticky headers are cloned by the table. Keep one popup per column
            // outside the cloned header and anchor it to the clicked button.
            const mount = () => document.querySelectorAll('[data-ui-column-filter-popover]').forEach((panel) => document.body.appendChild(panel));
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
            else mount();
            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-ui-column-filter-button]');
                if (!button) return;
                event.preventDefault();
                const panel = document.getElementById(button.getAttribute('popovertarget'));
                if (!panel) return;
                anchors.set(panel.id, button);
                panel.togglePopover();
                if (panel.matches(':popover-open')) position(panel);
            });
            document.addEventListener('toggle', (event) => {
                const panel = event.target;
                if (!panel.matches?.('[data-ui-column-filter-popover]') || event.newState !== 'open') return;
                position(panel);
                panel.querySelector('input')?.focus();
            }, true);
            document.addEventListener('input', (event) => {
                const input = event.target.closest('[data-ui-column-filter-name]');
                if (!input) return;
                const form = document.getElementById(input.dataset.uiColumnFilterForm);
                const field = form?.elements.namedItem(input.dataset.uiColumnFilterName);
                if (!field) return;
                field.value = input.value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                document.querySelectorAll('[data-ui-column-filter-name]').forEach((other) => {
                    if (other.dataset.uiColumnFilterForm !== input.dataset.uiColumnFilterForm || other.dataset.uiColumnFilterName !== input.dataset.uiColumnFilterName) return;
                    other.value = input.value;
                    document.querySelectorAll(`[popovertarget="${other.closest('[popover]').id}"]`).forEach((button) => {
                        button.classList.toggle('text-indigo-600', input.value.trim() !== '');
                        button.classList.toggle('dark:text-indigo-400', input.value.trim() !== '');
                    });
                });
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && event.target.matches('[data-ui-column-filter-name]')) event.preventDefault();
            });
            const reposition = () => document.querySelectorAll('[data-ui-column-filter-popover]:popover-open').forEach(position);
            window.addEventListener('resize', reposition);
            document.addEventListener('scroll', reposition, true);
        })();
    </script>
@endonce
