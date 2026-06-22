@props([
    'title' => null,
    'description' => null,
    'contained' => true,
    'tableClass' => null,
    'bodyId' => null,
])

@php
    $hasHeader = $title || $description || (isset($actions) && trim($actions->toHtml()) !== '');
    $hasFoot = isset($foot) && trim($foot->toHtml()) !== '';
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
        <div class="-mx-4 -my-2 sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle">
                <table class="min-w-full border-separate border-spacing-0 {{ $tableClass }}">
                    @isset($head)
                        <thead>
                            {{ $head }}
                        </thead>
                    @endisset

                    <tbody @if ($bodyId) id="{{ $bodyId }}" @endif>
                        {{ $slot }}
                    </tbody>

                    @if ($hasFoot)
                        <tfoot>
                            {{ $foot }}
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
