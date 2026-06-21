<?php

use App\Console\Commands\ImportBankDirectories;
use App\Console\Commands\SetApiCredential;
use App\Console\Commands\SyncTinkoffBank;
use App\Console\Commands\UpsertUser;
use App\Http\Middleware\RequireAdminBasicAuth;
use App\Http\Middleware\RequireAdminSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ImportBankDirectories::class,
        SetApiCredential::class,
        SyncTinkoffBank::class,
        UpsertUser::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.basic' => RequireAdminBasicAuth::class,
            'admin.session' => RequireAdminSession::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'internal/tinkoff/sync',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
