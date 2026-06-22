@props([
    'id',
    'title' => null,
    'description' => null,
    'size' => 'lg',
    'closeLabel' => 'Закрыть',
])

@php
    $sizes = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['lg'];
@endphp

<dialog
    id="{{ $id }}"
    {{ $attributes->class('fixed inset-0 size-auto max-h-none max-w-none overflow-y-auto bg-transparent p-0 text-left backdrop:bg-transparent') }}
    data-ui-modal
>
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/50" data-ui-modal-close></div>

    <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl ring-1 ring-gray-950/10 sm:my-8 sm:w-full {{ $sizeClass }} dark:bg-gray-800 dark:ring-white/10" data-ui-modal-panel>
            @if ($title || $description)
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-white/10">
                    <div class="min-w-0">
                        @if ($title)
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                        @endif

                        @if ($description)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                        @endif
                    </div>

                    <button
                        class="grid size-8 shrink-0 place-items-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:hover:bg-white/10 dark:hover:text-gray-300"
                        type="button"
                        title="{{ $closeLabel }}"
                        data-ui-modal-close
                    >
                        <span class="sr-only">{{ $closeLabel }}</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
</dialog>

@once
    <script>
        (() => {
            const openModal = (dialog) => {
                if (!dialog) {
                    return;
                }

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    return;
                }

                dialog.setAttribute('open', 'open');
            };

            const closeModal = (dialog) => {
                if (!dialog) {
                    return;
                }

                if (typeof dialog.close === 'function') {
                    dialog.close();
                    return;
                }

                dialog.removeAttribute('open');
            };

            document.addEventListener('click', (event) => {
                const openButton = event.target.closest('[data-ui-modal-open]');

                if (openButton) {
                    const dialog = document.getElementById(openButton.getAttribute('data-ui-modal-open'));
                    openModal(dialog);
                    return;
                }

                const closeButton = event.target.closest('[data-ui-modal-close]');

                if (closeButton) {
                    const targetId = closeButton.getAttribute('data-ui-modal-close');
                    const dialog = targetId ? document.getElementById(targetId) : closeButton.closest('dialog');
                    closeModal(dialog);
                }
            });
        })();
    </script>
@endonce
