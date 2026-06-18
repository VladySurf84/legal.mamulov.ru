@extends('layouts.app', ['title' => 'Планировщик'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Планировщик</h1>
            <div class="subtle">Задачи Laravel scheduler, которые запускаются через <span class="code">schedule:run</span>.</div>
        </div>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Команда</th>
                <th>Расписание</th>
                <th>Следующий запуск</th>
                <th>Ограничения</th>
                <th>Лог</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tasks as $task)
                <tr>
                    <td>
                        <strong>{{ $task['description'] ?: 'Задача' }}</strong>
                        <div class="subtle code">{{ $task['command'] }}</div>
                    </td>
                    <td>
                        <span class="code">{{ $task['expression'] }}</span>
                        <div class="subtle">{{ $task['timezone'] }}</div>
                    </td>
                    <td>
                        {{ $task['next_run_label'] }}
                        <div class="subtle">{{ $task['next_run_diff'] }}</div>
                    </td>
                    <td>
                        <div class="badges">
                            @if ($task['without_overlapping'])
                                <span class="badge">без наложения</span>
                            @endif
                            @if ($task['on_one_server'])
                                <span class="badge">один сервер</span>
                            @endif
                            @if (! $task['without_overlapping'] && ! $task['on_one_server'])
                                <span class="subtle">нет</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if ($task['output_path'])
                            <span class="code">{{ $task['output_path'] }}</span>
                            @if ($task['output_exists'])
                                <div class="subtle">
                                    {{ $task['output_size'] }} · изменен {{ $task['output_updated_at'] }}
                                </div>
                            @else
                                <div class="subtle">файл пока не создан</div>
                            @endif
                        @else
                            <span class="subtle">не задан</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Задачи планировщика пока не настроены.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
