@extends('layouts.app', ['title' => 'Планировщик'])

@section('content')
    <style>
        .tree { display: grid; gap: 12px; }
        .tree-node { border-bottom: 1px solid var(--line); }
        .tree-node:last-child { border-bottom: 0; }
        .tree-summary {
            display: grid;
            grid-template-columns: minmax(320px, 1.4fr) minmax(180px, .7fr) minmax(190px, .8fr) minmax(220px, 1fr);
            gap: 16px;
            padding: 14px;
            cursor: pointer;
            align-items: start;
        }
        .tree-summary::-webkit-details-marker,
        .run-summary::-webkit-details-marker { display: none; }
        .tree-title { display: flex; gap: 8px; align-items: baseline; }
        .tree-title::before { content: "▸"; color: var(--muted); font-size: 12px; }
        details[open] > .tree-summary .tree-title::before { content: "▾"; }
        .tree-children { padding: 0 14px 14px 38px; }
        .run-list, .request-list { display: grid; gap: 8px; }
        .run-node, .request-row {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #ffffff;
        }
        .run-summary {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) minmax(180px, .8fr) minmax(180px, .8fr);
            gap: 12px;
            padding: 10px 12px;
            cursor: pointer;
        }
        .request-row {
            display: grid;
            grid-template-columns: 90px minmax(220px, 1fr) 90px 110px minmax(220px, 1fr);
            gap: 12px;
            padding: 10px 12px;
            align-items: start;
        }
        .status {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef2f6;
            color: #475467;
        }
        .status.success { background: #e8f5ee; color: #256146; }
        .status.failed { background: #fff4f5; color: #8f2633; }
        .status.started { background: #fff7e6; color: #8a5a00; }
        @media (max-width: 980px) {
            .tree-summary, .run-summary, .request-row { grid-template-columns: 1fr; }
            .tree-children { padding-left: 14px; }
        }
    </style>

    <div class="page-head">
        <div>
            <h1>Планировщик</h1>
            <div class="subtle">Иерархия: задача планировщика → запуск синхронизации → HTTP-запросы.</div>
        </div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="errors">{{ session('error') }}</div>
    @endif

    <div class="panel">
        <div class="tree">
            @forelse ($tasks as $task)
                <details class="tree-node" open>
                    <summary class="tree-summary">
                        <div>
                            <div class="tree-title">
                                <strong>{{ $task['description'] ?: 'Задача' }}</strong>
                                <span class="status">scheduler</span>
                            </div>
                            <div class="subtle code">{{ $task['command'] }}</div>
                            @if ($task['run_route'])
                                <form method="post" action="{{ $task['run_route'] }}" style="margin-top: 10px;" onclick="event.stopPropagation();">
                                    @csrf
                                    <button type="submit">Запустить задание</button>
                                </form>
                            @endif
                        </div>
                        <div>
                            <div class="subtle">Расписание</div>
                            <div class="code">{{ $task['expression'] }}</div>
                            <div class="subtle">{{ $task['timezone'] }}</div>
                        </div>
                        <div>
                            <div class="subtle">Следующий запуск</div>
                            <div>{{ $task['next_run_label'] }}</div>
                            <div class="subtle">{{ $task['next_run_diff'] }}</div>
                        </div>
                        <div>
                            <div class="badges">
                                @if ($task['without_overlapping'])
                                    <span class="badge">без наложения</span>
                                @endif
                                @if ($task['on_one_server'])
                                    <span class="badge">один сервер</span>
                                @endif
                                @if (! $task['without_overlapping'] && ! $task['on_one_server'])
                                    <span class="subtle">ограничений нет</span>
                                @endif
                            </div>
                            @if ($task['output_path'])
                                <div class="subtle code" style="margin-top: 6px;">{{ $task['output_path'] }}</div>
                                @if ($task['output_exists'])
                                    <div class="subtle">{{ $task['output_size'] }} · изменен {{ $task['output_updated_at'] }}</div>
                                @else
                                    <div class="subtle">лог пока не создан</div>
                                @endif
                            @endif
                        </div>
                    </summary>

                    <div class="tree-children">
                        @if (count($task['runs']) > 0)
                            <div class="run-list">
                                @foreach ($task['runs'] as $run)
                                    <details class="run-node" @if ($loop->first) open @endif>
                                        <summary class="run-summary">
                                            <div>
                                                <strong>Запуск #{{ $run->api_sync_run_id }}</strong>
                                                <span class="status {{ $run->status }}">{{ $run->status }}</span>
                                                <div class="subtle">{{ $run->started_at_label }} → {{ $run->finished_at_label ?? 'в процессе' }}</div>
                                            </div>
                                            <div>
                                                <div class="subtle">Период</div>
                                                <div>{{ $run->period_from ?? '—' }} → {{ $run->period_till ?? '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="subtle">Результат</div>
                                                <div>{{ $run->accounts_count }} счетов · {{ $run->operations_count }} операций</div>
                                            </div>
                                            <div>
                                                <div class="subtle">HTTP-запросы</div>
                                                <div>{{ $run->requests_count }}</div>
                                                <div class="subtle">{{ $run->started_by_label }}</div>
                                            </div>
                                        </summary>

                                        <div class="tree-children">
                                            @if ($run->error)
                                                <div class="errors">{{ $run->error }}</div>
                                            @endif

                                            @if (count($run->requests) > 0)
                                                <div class="request-list">
                                                    @foreach ($run->requests as $request)
                                                        <div class="request-row">
                                                            <div>
                                                                <span class="status {{ $request->http_status >= 400 || $request->error ? 'failed' : 'success' }}">
                                                                    {{ $request->http_status ?? 'error' }}
                                                                </span>
                                                                <div class="subtle">{{ $request->duration_ms }} ms</div>
                                                            </div>
                                                            <div>
                                                                <strong>{{ $request->method }} {{ $request->endpoint }}</strong>
                                                                <div class="subtle code">{{ $request->requested_at }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="subtle">Провайдер</div>
                                                                <div>{{ $request->provider }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="subtle">Хэш</div>
                                                                <div class="code">{{ $request->response_hash ? substr($request->response_hash, 0, 10) : '—' }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="subtle">Параметры</div>
                                                                <div class="code">{{ $request->params ?: '{}' }}</div>
                                                                @if ($request->error)
                                                                    <div class="errors" style="margin-top: 8px;">{{ $request->error }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="subtle">HTTP-запросов для запуска пока нет.</div>
                                            @endif
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @else
                            <div class="subtle">Запусков для этой задачи пока нет.</div>
                        @endif
                    </div>
                </details>
            @empty
                <div style="padding: 14px;">Задачи планировщика пока не настроены.</div>
            @endforelse
        </div>
    </div>
@endsection
