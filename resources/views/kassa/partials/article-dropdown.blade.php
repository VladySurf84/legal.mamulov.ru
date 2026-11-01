<script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>

<el-dropdown class="block min-w-0" data-kassa-article-dropdown>
    <button type="button" class="inline-flex w-full items-center justify-between gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
        <span class="truncate" data-kassa-article-dropdown-label>Все статьи</span>
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="-mr-1 size-5 shrink-0 text-gray-400">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>

    <el-menu anchor="bottom end" popover class="z-30 max-h-96 w-64 origin-top-right overflow-y-auto rounded-md bg-white shadow-lg outline-1 outline-black/5 transition transition-discrete [--anchor-gap:--spacing(2)] data-closed:scale-95 data-closed:transform data-closed:opacity-0 data-enter:duration-100 data-enter:ease-out data-leave:duration-75 data-leave:ease-in dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
        <div class="py-1">
            @foreach ($articleFilterOptions as $option)
                <button type="button" role="menuitemcheckbox" aria-checked="{{ in_array((int) $option['value'], $filters['article_id'] ?? [], true) ? 'true' : 'false' }}" data-kassa-article-option="{{ $option['value'] }}" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 focus:bg-gray-100 focus:text-gray-900 focus:outline-hidden dark:text-gray-300 dark:focus:bg-white/5 dark:focus:text-white">
                    <span class="w-4 shrink-0" aria-hidden="true" data-kassa-article-check>{{ in_array((int) $option['value'], $filters['article_id'] ?? [], true) ? '✓' : '' }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $option['label'] }}</span>
                    <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $option['amount'] }}</span>
                </button>
            @endforeach
        </div>
    </el-menu>
</el-dropdown>

<script>
    (() => {
        const init = () => {
            const dropdown = document.querySelector('[data-kassa-article-dropdown]');
            const form = dropdown?.closest('form');
            if (!form || dropdown.dataset.ready) return;
            dropdown.dataset.ready = 'true';
            const inputs = () => [...form.querySelectorAll('input[data-multi-select-option][name="article_id[]"]')];
            const sync = () => {
                const selected = inputs().filter(input => input.checked);
                dropdown.querySelector('[data-kassa-article-dropdown-label]').textContent = selected.length === 0
                    ? 'Все статьи' : selected.length === 1 ? selected[0].dataset.label : `Выбрано ${selected.length}`;
                dropdown.querySelectorAll('[data-kassa-article-option]').forEach(button => {
                    const checked = selected.some(input => input.value === button.dataset.kassaArticleOption);
                    button.setAttribute('aria-checked', String(checked));
                    button.querySelector('[data-kassa-article-check]').textContent = checked ? '✓' : '';
                });
            };
            dropdown.addEventListener('click', event => {
                const button = event.target.closest('[data-kassa-article-option]');
                if (!button) return;
                const input = inputs().find(input => input.value === button.dataset.kassaArticleOption);
                if (!input) return;
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            form.addEventListener('change', sync);
            form.addEventListener('reset', () => setTimeout(sync, 0));
            sync();
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
        else init();
    })();
</script>
