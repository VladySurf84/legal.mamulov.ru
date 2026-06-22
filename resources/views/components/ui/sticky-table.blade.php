@props([
    'title' => null,
    'description' => null,
    'contained' => false,
    'tableClass' => null,
    'bodyId' => null,
    'footId' => null,
    'scrollable' => false,
    'scrollClass' => 'max-h-[calc(100vh-1rem)] overflow-auto',
    'viewportSticky' => false,
    'stickySummaryEnabled' => false,
    'topScrollbar' => false,
    'bottomScrollbar' => false,
])

@php
    $hasHeader = $title || $description || (isset($actions) && trim($actions->toHtml()) !== '');
    $hasFoot = isset($foot) && trim($foot->toHtml()) !== '';
    $hasStickySummary = isset($stickySummary) && trim($stickySummary->toHtml()) !== '';
    $hasTableFoot = $hasFoot || ($stickySummaryEnabled && $hasStickySummary);
    $tableUid = 'sticky-table-' . uniqid();
@endphp

<div {{ $attributes->class($contained ? 'px-4 sm:px-6 lg:px-8' : '') }}>
    @if ($hasHeader)
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                @if ($title)
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="{{ $hasHeader ? 'mt-8' : '' }} flow-root">
        @if ($topScrollbar)
            <div class="-mx-4 mb-2 h-4 overflow-x-auto overflow-y-hidden sm:-mx-6 lg:-mx-8" data-ui-sticky-table-top-scroll="{{ $tableUid }}">
                <div class="h-px" data-ui-sticky-table-top-scroll-spacer></div>
            </div>
        @endif

        @if ($bottomScrollbar)
            <div class="fixed bottom-0 z-50 hidden h-4 overflow-x-auto overflow-y-hidden bg-white/90 shadow-[0_-1px_0_rgba(148,163,184,0.45)] backdrop-blur-sm dark:bg-gray-900/90" data-ui-sticky-table-bottom-scroll="{{ $tableUid }}">
                <div class="h-px" data-ui-sticky-table-bottom-scroll-spacer></div>
            </div>
        @endif

        <div @class([
            '-mx-4 -my-2 sm:-mx-6 lg:-mx-8',
            $scrollClass => $scrollable,
            '[scrollbar-width:none] [&::-webkit-scrollbar]:hidden' => $bottomScrollbar,
            '[&_tfoot_.sticky]:!static' => $viewportSticky,
        ]) @if ($viewportSticky || $topScrollbar || $bottomScrollbar) data-ui-sticky-table-scroll="{{ $tableUid }}" @endif>
            <div @class([
                'inline-block min-w-full align-middle',
                'py-4' => $bottomScrollbar,
                'py-2' => ! $bottomScrollbar,
            ])>
                <table class="w-full min-w-full border-separate border-spacing-0 {{ $tableClass }}" @if ($viewportSticky || $topScrollbar || $bottomScrollbar) data-ui-sticky-table @endif>
                    @isset($head)
                        <thead>
                            {{ $head }}
                        </thead>
                    @endisset

                    <tbody @if ($bodyId) id="{{ $bodyId }}" @endif>
                        {{ $slot }}
                    </tbody>

                    @if ($hasTableFoot)
                        <tfoot
                            @if ($footId) id="{{ $footId }}" @endif
                            @if ($stickySummaryEnabled && $hasStickySummary) data-ui-sticky-table-summary-body @endif
                            @if ($stickySummaryEnabled && $hasStickySummary) style="visibility: hidden;" aria-hidden="true" @endif
                        >
                            @if ($hasFoot)
                                {{ $foot }}
                            @else
                                {{ $stickySummary }}
                            @endif
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

@if ($viewportSticky)
    @once
        <script>
            (() => {
                const overlayClass = 'fixed z-40 hidden overflow-hidden';
                const tableOverlayClass = 'table-fixed border-separate border-spacing-0';

                const makeColumnGroup = (table) => {
                    const row = table.tHead?.rows?.[0] || table.tBodies?.[0]?.rows?.[0] || table.tFoot?.rows?.[0];
                    const group = document.createElement('colgroup');

                    if (!row) {
                        return group;
                    }

                    Array.from(row.cells).forEach((cell) => {
                        const span = Math.max(1, cell.colSpan || 1);
                        const width = cell.getBoundingClientRect().width / span;

                        for (let index = 0; index < span; index += 1) {
                            const col = document.createElement('col');
                            col.style.width = `${width}px`;
                            group.appendChild(col);
                        }
                    });

                    return group;
                };

                const syncColumnGroup = (table, cloneTable) => {
                    cloneTable.classList.add('table-fixed');

                    const nextGroup = makeColumnGroup(table);
                    const currentGroup = cloneTable.querySelector('colgroup');

                    if (currentGroup) {
                        currentGroup.replaceWith(nextGroup);
                        return;
                    }

                    cloneTable.prepend(nextGroup);
                };

                const buildOverlay = (table, sectionName) => {
                    const section = sectionName === 'thead' ? table.tHead : table.tFoot;

                    if (!section) {
                        return null;
                    }

                    const overlay = document.createElement('div');
                    overlay.className = overlayClass;
                    overlay.setAttribute('aria-hidden', 'true');

                    if (sectionName === 'tfoot') {
                        overlay.setAttribute('data-ui-sticky-table-footer-overlay', '');
                    }

                    const cloneTable = document.createElement('table');
                    const cloneSection = section.cloneNode(true);

                    if (sectionName === 'tfoot') {
                        cloneSection.style.visibility = 'visible';
                        cloneSection.removeAttribute('aria-hidden');
                    }

                    cloneSection.querySelectorAll('.sticky').forEach((element) => {
                        element.classList.remove('sticky', 'top-0', 'bottom-0');
                        element.style.position = 'static';
                        element.style.top = 'auto';
                        element.style.bottom = 'auto';
                    });

                    cloneTable.className = `${tableOverlayClass} ${table.className}`;
                    cloneTable.appendChild(makeColumnGroup(table));
                    cloneTable.appendChild(cloneSection);

                    overlay.appendChild(cloneTable);
                    document.body.appendChild(overlay);

                    return { overlay, cloneTable };
                };

                const findHorizontalScrollbars = (scrollBox) => {
                    const tableId = scrollBox.getAttribute('data-ui-sticky-table-scroll');
                    const previous = scrollBox.previousElementSibling;
                    const scrollbars = [];

                    if (tableId) {
                        const escapedTableId = CSS.escape(tableId);
                        const top = document.querySelector(`[data-ui-sticky-table-top-scroll="${escapedTableId}"]`);
                        const bottom = document.querySelector(`[data-ui-sticky-table-bottom-scroll="${escapedTableId}"]`);

                        if (top) {
                            scrollbars.push({ element: top, placement: 'top' });
                        }

                        if (bottom) {
                            scrollbars.push({ element: bottom, placement: 'bottom' });
                        }

                        return scrollbars;
                    }

                    if (previous?.matches('[data-ui-sticky-table-top-scroll]')) {
                        scrollbars.push({ element: previous, placement: 'top' });
                    }

                    return scrollbars;
                };

                const syncHorizontalScrollbars = (scrollbars, scrollBox, table) => {
                    if (!scrollbars.length) {
                        return;
                    }

                    const scrollRect = scrollBox.getBoundingClientRect();
                    const tableRect = table.getBoundingClientRect();
                    const tableWidth = table.getBoundingClientRect().width;
                    const hasHorizontalRange = scrollBox.scrollWidth > scrollBox.clientWidth;
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    const tableVisible = tableRect.bottom > 0 && tableRect.top < viewportHeight;

                    scrollbars.forEach(({ element, placement }) => {
                        const spacer = element.querySelector(
                            placement === 'bottom'
                                ? '[data-ui-sticky-table-bottom-scroll-spacer]'
                                : '[data-ui-sticky-table-top-scroll-spacer]'
                        );
                        const visible = hasHorizontalRange && (placement !== 'bottom' || tableVisible);

                        if (spacer) {
                            spacer.style.width = `${tableWidth}px`;
                        }

                        element.classList.toggle('hidden', !visible);

                        if (placement === 'bottom' && visible) {
                            element.style.left = `${scrollRect.left}px`;
                            element.style.width = `${scrollRect.width}px`;
                        }

                        if (element.scrollLeft !== scrollBox.scrollLeft) {
                            element.scrollLeft = scrollBox.scrollLeft;
                        }
                    });
                };

                const syncOverlay = (scrollBox, table, item, placement) => {
                    if (!item) {
                        return;
                    }

                    const scrollRect = scrollBox.getBoundingClientRect();
                    const tableRect = table.getBoundingClientRect();
                    const section = placement === 'top' ? table.tHead : table.tFoot;
                    const sectionRect = section?.getBoundingClientRect();
                    const sectionHeight = sectionRect?.height || 0;
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    const viewportBottom = placement === 'bottom'
                        ? viewportHeight - getBottomScrollbarOffset(scrollBox)
                        : viewportHeight;
                    const hasVerticalRange = tableRect.height > sectionHeight * 3;

                    const visible = placement === 'top'
                        ? tableRect.top < 0 && tableRect.bottom > sectionHeight && hasVerticalRange
                        : tableRect.top < viewportBottom - sectionHeight
                            && tableRect.bottom > sectionHeight
                            && hasVerticalRange;

                    item.overlay.classList.toggle('hidden', !visible);

                    if (!visible) {
                        return;
                    }

                    item.overlay.style.left = `${scrollRect.left}px`;
                    item.overlay.style.width = `${scrollRect.width}px`;
                    item.overlay.style.top = placement === 'top' ? '0px' : '';
                    item.overlay.style.bottom = placement === 'bottom' ? `${getBottomScrollbarOffset(scrollBox)}px` : '';
                    syncColumnGroup(table, item.cloneTable);
                    item.cloneTable.style.width = `${table.getBoundingClientRect().width}px`;
                    item.cloneTable.style.transform = `translateX(${-scrollBox.scrollLeft}px)`;
                };

                const getBottomScrollbarOffset = (scrollBox) => {
                    const tableId = scrollBox.getAttribute('data-ui-sticky-table-scroll');

                    if (!tableId) {
                        return 0;
                    }

                    const bottomScrollbar = document.querySelector(`[data-ui-sticky-table-bottom-scroll="${CSS.escape(tableId)}"]`);

                    if (!bottomScrollbar || bottomScrollbar.classList.contains('hidden')) {
                        return 0;
                    }

                    return bottomScrollbar.getBoundingClientRect().height || 0;
                };

                const initStickyTable = (scrollBox) => {
                    if (scrollBox.dataset.uiStickyTableReady === 'true') {
                        return;
                    }

                    const table = scrollBox.querySelector('[data-ui-sticky-table]');

                    if (!table) {
                        return;
                    }

                    scrollBox.dataset.uiStickyTableReady = 'true';

                    const horizontalScrollbars = findHorizontalScrollbars(scrollBox);
                    let header = buildOverlay(table, 'thead');
                    let footer = buildOverlay(table, 'tfoot');
                    let syncingScrollLeft = false;
                    let lastScrollbarScrollLeft = horizontalScrollbars[0]?.element.scrollLeft || 0;
                    let lastBoxScrollLeft = scrollBox.scrollLeft;

                    const update = () => {
                        syncHorizontalScrollbars(horizontalScrollbars, scrollBox, table);
                        syncOverlay(scrollBox, table, header, 'top');
                        syncOverlay(scrollBox, table, footer, 'bottom');
                    };

                    const refresh = () => {
                        header?.overlay.remove();
                        footer?.overlay.remove();
                        header = buildOverlay(table, 'thead');
                        footer = buildOverlay(table, 'tfoot');
                        update();
                    };

                    const queueRefresh = () => {
                        window.requestAnimationFrame(() => {
                            window.requestAnimationFrame(refresh);
                        });
                    };

                    scrollBox.addEventListener('scroll', () => {
                        if (horizontalScrollbars.length && !syncingScrollLeft) {
                            syncingScrollLeft = true;
                            horizontalScrollbars.forEach(({ element }) => {
                                element.scrollLeft = scrollBox.scrollLeft;
                            });
                            syncingScrollLeft = false;
                        }

                        update();
                    }, { passive: true });

                    horizontalScrollbars.forEach(({ element }) => {
                        element.addEventListener('scroll', () => {
                            if (syncingScrollLeft) {
                                return;
                            }

                            syncingScrollLeft = true;
                            scrollBox.scrollLeft = element.scrollLeft;
                            horizontalScrollbars.forEach(({ element: otherElement }) => {
                                if (otherElement !== element) {
                                    otherElement.scrollLeft = element.scrollLeft;
                                }
                            });
                            syncingScrollLeft = false;
                            update();
                        }, { passive: true });

                        element.addEventListener('wheel', (event) => {
                            const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;

                            if (!delta) {
                                return;
                            }

                            event.preventDefault();
                            element.scrollLeft += delta;
                            scrollBox.scrollLeft = element.scrollLeft;

                            horizontalScrollbars.forEach(({ element: otherElement }) => {
                                if (otherElement !== element) {
                                    otherElement.scrollLeft = element.scrollLeft;
                                }
                            });

                            update();
                        }, { passive: false });
                    });

                    const watchScrollLeft = () => {
                        if (horizontalScrollbars.length) {
                            const currentScrollbarScrollLeft = horizontalScrollbars[0].element.scrollLeft;
                            const currentBoxScrollLeft = scrollBox.scrollLeft;

                            if (currentScrollbarScrollLeft !== lastScrollbarScrollLeft) {
                                scrollBox.scrollLeft = currentScrollbarScrollLeft;
                                horizontalScrollbars.forEach(({ element }) => {
                                    element.scrollLeft = currentScrollbarScrollLeft;
                                });
                                lastBoxScrollLeft = currentScrollbarScrollLeft;
                                update();
                            } else if (currentBoxScrollLeft !== lastBoxScrollLeft) {
                                horizontalScrollbars.forEach(({ element }) => {
                                    element.scrollLeft = currentBoxScrollLeft;
                                });
                                lastScrollbarScrollLeft = currentBoxScrollLeft;
                                update();
                            }

                            lastScrollbarScrollLeft = horizontalScrollbars[0].element.scrollLeft;
                            lastBoxScrollLeft = scrollBox.scrollLeft;
                        }

                        window.requestAnimationFrame(watchScrollLeft);
                    };

                    window.requestAnimationFrame(watchScrollLeft);

                    window.addEventListener('scroll', update, { passive: true });
                    window.addEventListener('resize', update);
                    window.addEventListener('load', queueRefresh, { once: true });
                    document.addEventListener('ui:sticky-table-refresh', refresh);

                    update();
                    queueRefresh();

                    if (document.fonts?.ready) {
                        document.fonts.ready.then(queueRefresh);
                    }
                };

                const initStickyTables = () => {
                    document.querySelectorAll('[data-ui-sticky-table-scroll]').forEach(initStickyTable);
                    document.dispatchEvent(new Event('ui:sticky-table-refresh'));
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initStickyTables);
                } else {
                    initStickyTables();
                }

                document.addEventListener('livewire:navigated', initStickyTables);
            })();
        </script>
    @endonce
@endif
