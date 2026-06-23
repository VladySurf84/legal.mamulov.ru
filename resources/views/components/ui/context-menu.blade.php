@props([
    'id' => null,
    'triggerClass' => '',
    'triggerSelector' => null,
    'menuClass' => '',
])

@php
    $menuId = $id ?: 'context-menu-' . uniqid();
    $triggerClasses = trim($triggerClass);
    $menuClasses = trim("fixed z-[70] hidden min-w-56 rounded-lg border border-gray-200 bg-white p-1 shadow-xl ring-1 ring-black/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 {$menuClass}");
@endphp

<div {{ $attributes->class($triggerSelector ? '' : 'relative') }} data-ui-context-menu-root>
    @if (! $triggerSelector)
        <div class="{{ $triggerClasses }}" data-ui-context-menu-trigger="{{ $menuId }}">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif

    <div
        id="{{ $menuId }}"
        class="{{ $menuClasses }}"
        role="menu"
        aria-orientation="vertical"
        data-ui-context-menu-panel
        @if ($triggerSelector) data-ui-context-menu-trigger-selector="{{ $triggerSelector }}" @endif
    >
        {{ $menu ?? '' }}
    </div>
</div>

@once
    <script>
        (() => {
            if (window.__uiContextMenuReady) {
                return;
            }

            window.__uiContextMenuReady = true;

            let activePanel = null;
            let rightButtonDownAt = null;
            const nativeMenuHoldMs = 1000;

            const closeActivePanel = () => {
                if (!activePanel) {
                    return;
                }

                activePanel.classList.add('hidden');
                activePanel = null;
            };

            const positionPanel = (panel, event) => {
                const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const margin = 8;

                panel.classList.remove('hidden');

                const rect = panel.getBoundingClientRect();
                const left = Math.min(event.clientX, viewportWidth - rect.width - margin);
                const top = Math.min(event.clientY, viewportHeight - rect.height - margin);

                panel.style.left = `${Math.max(margin, left)}px`;
                panel.style.top = `${Math.max(margin, top)}px`;
            };

            const focusFirstItem = (panel) => {
                const item = panel.querySelector('[role="menuitem"]:not([disabled])');
                item?.focus?.();
            };

            const panelForEvent = (event) => {
                const trigger = event.target.closest('[data-ui-context-menu-trigger]');

                if (trigger) {
                    return document.getElementById(trigger.dataset.uiContextMenuTrigger);
                }

                for (const panel of document.querySelectorAll('[data-ui-context-menu-trigger-selector]')) {
                    if (event.target.closest(panel.dataset.uiContextMenuTriggerSelector)) {
                        return panel;
                    }
                }

                return null;
            };

            document.addEventListener('contextmenu', (event) => {
                const panel = panelForEvent(event);

                if (!panel) {
                    return;
                }

                const pressedForMs = rightButtonDownAt === null ? 0 : Date.now() - rightButtonDownAt;
                rightButtonDownAt = null;

                if (pressedForMs >= nativeMenuHoldMs) {
                    closeActivePanel();
                    return;
                }

                event.preventDefault();
                closeActivePanel();
                activePanel = panel;
                positionPanel(panel, event);
                focusFirstItem(panel);
            });

            document.addEventListener('mousedown', (event) => {
                if (event.button === 2 && panelForEvent(event)) {
                    rightButtonDownAt = Date.now();
                }
            }, { capture: true });

            document.addEventListener('mouseup', (event) => {
                if (event.button === 2) {
                    window.setTimeout(() => {
                        rightButtonDownAt = null;
                    }, 0);
                }
            }, { capture: true });

            document.addEventListener('click', (event) => {
                if (!activePanel) {
                    return;
                }

                if (!activePanel.contains(event.target) || event.target.closest('[role="menuitem"]')) {
                    closeActivePanel();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeActivePanel();
                }
            });

            window.addEventListener('resize', closeActivePanel);
            window.addEventListener('scroll', closeActivePanel, true);
        })();
    </script>
@endonce
