@extends('layouts.app', [
    'title' => 'Legal Internal API',
    'titleDescription' => 'Внутреннее API legal.mamulov.ru для синхронизации электронных подписей и удаленной подписи данных через CryptoPro на сервере.',
])

@section('title_after')
    <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-md px-2 py-1 text-xs font-medium text-white ring-1 ring-gray-500" style="background-color: #7d8492;">1.0.0</span>
        <span class="rounded-md px-2 py-1 text-xs font-medium text-white ring-1 ring-green-600" style="background-color: #89bf04;">OAS 3.0</span>
    </div>
@endsection

@section('title_meta')
    <a class="break-all font-mono text-xs font-medium text-indigo-600 hover:text-indigo-500"
       href="{{ route('internal-api-docs.spec') }}"
       target="_blank">
        {{ route('internal-api-docs.spec') }}
    </a>
@endsection

@section('content')
    <div class="overflow-hidden bg-white">
        <div id="swagger-ui" class="min-h-[70vh]"></div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        #swagger-ui .information-container {
            display: none;
        }

        #swagger-ui .wrapper {
            max-width: none;
            padding-left: 0;
            padding-right: 0;
        }

        #swagger-ui .opblock-tag,
        #swagger-ui .opblock {
            margin-left: 0;
            margin-right: 0;
        }

        #swagger-ui .scheme-container {
            padding-top: 0;
            box-shadow: none;
        }
    </style>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.addEventListener('load', () => {
            window.ui = SwaggerUIBundle({
                url: @json(route('internal-api-docs.spec')),
                dom_id: '#swagger-ui',
                deepLinking: true,
                persistAuthorization: true,
                layout: 'BaseLayout',
            });
        });
    </script>
@endsection
