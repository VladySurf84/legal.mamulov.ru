@props([
    'id',
    'title' => null,
    'description' => null,
    'size' => 'md',
    'side' => 'right',
    'closeLabel' => 'Закрыть панель',
    'open' => false,
])

@php
    $sizes = [
        'auto' => 'w-max min-w-64 max-w-[calc(100vw-2.5rem)]',
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $isLeft = $side === 'left';
    $focusWrapperClass = $isLeft
        ? 'absolute inset-0 pr-10 focus:outline-none sm:pr-16'
        : 'absolute inset-0 pl-10 focus:outline-none sm:pl-16';
    $panelClass = $isLeft
        ? 'group/dialog-panel relative mr-auto block h-full ' . $sizeClass . ' transform transition duration-[250ms] ease-in-out data-[ui-closed]:-translate-x-full sm:duration-[350ms]'
        : 'group/dialog-panel relative ml-auto block h-full ' . $sizeClass . ' transform transition duration-[250ms] ease-in-out data-[ui-closed]:translate-x-full sm:duration-[350ms]';
    $closeWrapperClass = $isLeft
        ? 'absolute top-0 right-0 -mr-8 flex pt-4 pl-2 duration-[250ms] ease-in-out group-data-[ui-closed]/dialog-panel:opacity-0 sm:-mr-10 sm:pl-4'
        : 'absolute top-0 left-0 -ml-8 flex pt-4 pr-2 duration-[250ms] ease-in-out group-data-[ui-closed]/dialog-panel:opacity-0 sm:-ml-10 sm:pr-4';
    $contentClass = $isLeft
        ? 'relative flex h-full flex-col overflow-y-auto bg-white py-6 shadow-xl dark:bg-gray-800 dark:after:absolute dark:after:inset-y-0 dark:after:right-0 dark:after:w-px dark:after:bg-white/10'
        : 'relative flex h-full flex-col overflow-y-auto bg-white py-6 shadow-xl dark:bg-gray-800 dark:after:absolute dark:after:inset-y-0 dark:after:left-0 dark:after:w-px dark:after:bg-white/10';
    $titleId = "{$id}-title";
@endphp

<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $title ? $titleId : null }}"
    {{ $attributes->class('fixed inset-0 size-auto max-h-none max-w-none overflow-hidden bg-transparent p-0 text-left backdrop:bg-transparent') }}
    data-ui-drawer
    @if ($open) data-ui-drawer-open-on-load @endif
>
    <div
        class="absolute inset-0 bg-gray-500/75 transition-opacity duration-[250ms] ease-in-out data-[ui-closed]:opacity-0 dark:bg-gray-900/50"
        data-ui-drawer-backdrop
        data-ui-drawer-close
    ></div>

    <div tabindex="0" class="{{ $focusWrapperClass }}" data-ui-drawer-surface>
        <div
            class="{{ $panelClass }}"
            data-ui-drawer-panel
        >
            <div class="{{ $closeWrapperClass }}">
                <button
                    type="button"
                    class="relative rounded-md text-gray-300 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-400 dark:hover:text-white dark:focus-visible:outline-indigo-500"
                    title="{{ $closeLabel }}"
                    data-ui-drawer-close
                >
                    <span class="absolute -inset-2.5"></span>
                    <span class="sr-only">{{ $closeLabel }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                        <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <div class="{{ $contentClass }}">
                @isset($header)
                    <div class="px-4 sm:px-6">
                        {{ $header }}
                    </div>
                @elseif ($title || $description)
                    <div class="px-4 sm:px-6">
                        @if ($title)
                            <h2 id="{{ $titleId }}" class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                        @endif

                        @if ($description)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                        @endif
                    </div>
                @endif

                <div class="relative mt-6 flex-1 px-4 sm:px-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</dialog>

@once
    <script>
        (() => {
            const animatedParts = (dialog) => [
                dialog?.querySelector('[data-ui-drawer-backdrop]'),
                dialog?.querySelector('[data-ui-drawer-panel]'),
            ].filter(Boolean);

            const setState = (dialog, state, enabled = true) => {
                animatedParts(dialog).forEach((part) => {
                    part.toggleAttribute(state, enabled);
                });
            };

            const resetState = (dialog) => {
                animatedParts(dialog).forEach((part) => {
                    part.removeAttribute('data-ui-enter');
                    part.removeAttribute('data-ui-leave');
                    part.removeAttribute('data-ui-closed');
                });
            };

            const openDrawer = (dialog) => {
                if (!dialog || dialog.open) {
                    return;
                }

                resetState(dialog);
                setState(dialog, 'data-ui-enter', true);
                setState(dialog, 'data-ui-closed', true);

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', 'open');
                }

                requestAnimationFrame(() => {
                    setState(dialog, 'data-ui-closed', false);

                    window.setTimeout(() => {
                        setState(dialog, 'data-ui-enter', false);
                    }, 350);
                });
            };

            const closeDrawer = (dialog) => {
                if (!dialog || !dialog.open || dialog.hasAttribute('data-ui-closing')) {
                    return;
                }

                dialog.setAttribute('data-ui-closing', 'true');
                setState(dialog, 'data-ui-enter', false);
                setState(dialog, 'data-ui-leave', true);
                setState(dialog, 'data-ui-closed', true);

                window.setTimeout(() => {
                    if (typeof dialog.close === 'function') {
                        dialog.close();
                    } else {
                        dialog.removeAttribute('open');
                    }

                    dialog.removeAttribute('data-ui-closing');
                    resetState(dialog);
                }, 250);
            };

            document.addEventListener('cancel', (event) => {
                const dialog = event.target.matches?.('[data-ui-drawer]') ? event.target : null;

                if (!dialog) {
                    return;
                }

                event.preventDefault();
                closeDrawer(dialog);
            }, true);

            document.addEventListener('click', (event) => {
                const openButton = event.target.closest('[data-ui-drawer-open]');

                if (openButton) {
                    const dialog = document.getElementById(openButton.getAttribute('data-ui-drawer-open'));
                    openDrawer(dialog);
                    return;
                }

                const surface = event.target.closest('[data-ui-drawer-surface]');

                if (surface && event.target === surface) {
                    closeDrawer(surface.closest('[data-ui-drawer]'));
                    return;
                }

                const closeButton = event.target.closest('[data-ui-drawer-close]');

                if (closeButton) {
                    event.preventDefault();

                    const targetId = closeButton.getAttribute('data-ui-drawer-close');
                    const dialog = targetId ? document.getElementById(targetId) : closeButton.closest('[data-ui-drawer]');
                    closeDrawer(dialog);
                }
            });

            const openInitialDrawers = () => {
                document.querySelectorAll('[data-ui-drawer][data-ui-drawer-open-on-load]').forEach((dialog) => {
                    openDrawer(dialog);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openInitialDrawers, { once: true });
            } else {
                openInitialDrawers();
            }
        })();
    </script>
@endonce
