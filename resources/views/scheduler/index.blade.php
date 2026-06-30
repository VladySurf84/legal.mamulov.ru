@extends('layouts.app', [
    'title' => 'Планировщик',
    'titleDescription' => 'Иерархия: задача планировщика -> запуск синхронизации -> HTTP-запросы к внешним API.',
])

@php
    $statusClasses = [
        'success' => 'bg-green-50 text-green-700 ring-green-600/20',
        'failed' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'started' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'running' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'scheduler' => 'bg-gray-50 text-gray-700 ring-gray-600/20',
    ];
    $badge = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1';
@endphp

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 ring-1 ring-indigo-600/20">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 ring-1 ring-rose-600/20">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5">
        @forelse ($tasks as $task)
            <details class="group border-b border-gray-200 last:border-b-0" open>
                <summary class="grid cursor-pointer list-none gap-4 px-4 py-4 hover:bg-gray-50 sm:grid-cols-[minmax(280px,1.4fr)_minmax(160px,.7fr)_minmax(180px,.8fr)_minmax(220px,1fr)] sm:px-6 [&::-webkit-details-marker]:hidden">
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xs text-gray-400 transition group-open:rotate-90">▶</span>
                            <span class="font-semibold text-gray-900">{{ $task['description'] ?: 'Задача' }}</span>
                            <span class="{{ $badge }} {{ $statusClasses['scheduler'] }}">scheduler</span>
                        </div>
                        <div class="mt-1 break-all font-mono text-xs text-gray-500">{{ $task['command'] }}</div>

                        @if ($task['run_route'] && $canRunScheduler)
                            <form method="post" action="{{ $task['run_route'] }}" class="mt-3" onclick="event.stopPropagation();">
                                @csrf
                                <x-ui.button type="submit" variant="soft">
                                    Запустить задание
                                </x-ui.button>
                            </form>
                        @endif
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-500">Расписание</div>
                        <div class="mt-1 font-mono text-sm text-gray-900">{{ $task['expression'] }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $task['timezone'] }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-500">Следующий запуск</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $task['next_run_label'] }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $task['next_run_diff'] }}</div>
                    </div>

                    <div>
                        <div class="flex flex-wrap gap-1.5">
                            @if ($task['without_overlapping'])
                                <span class="{{ $badge }} bg-indigo-50 text-indigo-700 ring-indigo-600/20">без наложения</span>
                            @endif
                            @if ($task['on_one_server'])
                                <span class="{{ $badge }} bg-sky-50 text-sky-700 ring-sky-600/20">один сервер</span>
                            @endif
                            @if (! $task['without_overlapping'] && ! $task['on_one_server'])
                                <span class="text-sm text-gray-500">ограничений нет</span>
                            @endif
                        </div>

                        @if ($task['output_path'])
                            <div class="mt-2 break-all font-mono text-xs text-gray-500">{{ $task['output_path'] }}</div>
                            @if ($task['output_exists'])
                                <div class="mt-1 text-xs text-gray-500">{{ $task['output_size'] }} · изменен {{ $task['output_updated_at'] }}</div>
                            @else
                                <div class="mt-1 text-xs text-gray-500">лог пока не создан</div>
                            @endif
                        @endif
                    </div>
                </summary>

                <div class="px-4 pb-5 sm:px-6">
                    @if (count($task['runs']) > 0)
                        <div class="space-y-3 pl-0 sm:pl-6">
                            @foreach ($task['runs'] as $run)
                                @php
                                    $runStatusClass = $statusClasses[$run->status] ?? $statusClasses['scheduler'];
                                @endphp
                                <details class="group/run rounded-lg bg-gray-50 ring-1 ring-gray-900/5" @if ($loop->first) open @endif>
                                    <summary class="grid cursor-pointer list-none gap-3 px-4 py-3 sm:grid-cols-[minmax(220px,1fr)_minmax(180px,.8fr)_minmax(160px,.7fr)_minmax(160px,.7fr)] [&::-webkit-details-marker]:hidden">
                                        <div>
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-xs text-gray-400 transition group-open/run:rotate-90">▶</span>
                                                <span class="font-semibold text-gray-900">Запуск #{{ $run->api_sync_run_id }}</span>
                                                <span class="{{ $badge }} {{ $runStatusClass }}">{{ $run->status }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $run->started_at_label }} -> {{ $run->finished_at_label ?? 'в процессе' }}</div>
                                        </div>

                                        <div>
                                            <div class="text-xs font-medium text-gray-500">Период</div>
                                            <div class="mt-1 text-sm text-gray-900">{{ $run->period_from ?? '—' }} -> {{ $run->period_till ?? '—' }}</div>
                                        </div>

                                        <div>
                                            <div class="text-xs font-medium text-gray-500">Результат</div>
                                            <div class="mt-1 text-sm text-gray-900">{{ $run->accounts_count }} счетов · {{ $run->operations_count }} операций</div>
                                        </div>

                                        <div>
                                            <div class="text-xs font-medium text-gray-500">HTTP-запросы</div>
                                            <div class="mt-1 text-sm text-gray-900">{{ $run->requests_count }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $run->started_by_label }}</div>
                                        </div>
                                    </summary>

                                    <div class="px-4 pb-4">
                                        @if ($run->error_label)
                                            <div class="mb-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700 ring-1 ring-rose-600/20">{{ $run->error_label }}</div>
                                        @endif

                                        @if (count($run->requests) > 0)
                                            <div class="space-y-2">
                                                @if ($run->requests_hidden_count > 0)
                                                    <div class="rounded-md bg-white px-3 py-2 text-xs text-gray-500 ring-1 ring-gray-900/5">
                                                        Показаны последние {{ $run->requests_shown_count }} из {{ $run->requests_count }} HTTP-запросов.
                                                    </div>
                                                @endif

                                                @foreach ($run->requests as $request)
                                                    @php
                                                        $requestFailed = ($request->http_status ?? 0) >= 400 || $request->error_label;
                                                        $requestStatusClass = $requestFailed ? $statusClasses['failed'] : $statusClasses['success'];
                                                    @endphp
                                                    <div class="grid gap-3 rounded-md bg-white px-3 py-3 text-sm ring-1 ring-gray-900/5 sm:grid-cols-[90px_minmax(220px,1fr)_100px_120px_minmax(220px,1fr)]">
                                                        <div>
                                                            <span class="{{ $badge }} {{ $requestStatusClass }}">{{ $request->http_status ?? 'error' }}</span>
                                                            <div class="mt-1 text-xs text-gray-500">{{ $request->duration_ms }} ms</div>
                                                            <a
                                                                href="{{ $request->response_route }}"
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="mt-2 inline-flex items-center rounded-md bg-white px-2 py-1 text-xs font-semibold text-indigo-700 shadow-xs ring-1 ring-indigo-200 hover:bg-indigo-50"
                                                            >
                                                                response
                                                            </a>
                                                        </div>
                                                        <div>
                                                            <div class="font-semibold text-gray-900">{{ $request->method }} {{ $request->endpoint }}</div>
                                                            <div class="mt-1 font-mono text-xs text-gray-500">{{ $request->requested_at_label }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs font-medium text-gray-500">Провайдер</div>
                                                            <div class="mt-1 text-gray-900">{{ $request->provider }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs font-medium text-gray-500">Хэш</div>
                                                            <div class="mt-1 font-mono text-xs text-gray-900">{{ $request->response_hash ? substr($request->response_hash, 0, 10) : '—' }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs font-medium text-gray-500">Параметры</div>
                                                            <div class="mt-1 break-all font-mono text-xs text-gray-900">{{ $request->params_label }}</div>
                                                            @if ($request->error_label)
                                                                <div class="mt-2 rounded-md bg-rose-50 px-2 py-1 text-xs text-rose-700 ring-1 ring-rose-600/20">{{ $request->error_label }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-500">HTTP-запросов для запуска пока нет.</div>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @else
                        <div class="pl-0 text-sm text-gray-500 sm:pl-6">Запусков для этой задачи пока нет.</div>
                    @endif
                </div>
            </details>
        @empty
            <div class="px-6 py-12 text-center text-sm text-gray-500">
                Задачи планировщика пока не настроены.
            </div>
        @endforelse
    </div>
@endsection
