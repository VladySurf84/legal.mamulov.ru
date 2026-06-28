<?php

use App\Console\Commands\ImportBankDirectories;
use App\Console\Commands\ImportBankStatement;
use App\Console\Commands\ImportOzonBankStatement;
use App\Console\Commands\AuthEdoLight;
use App\Console\Commands\RebuildCashLayer;
use App\Console\Commands\SetApiCredential;
use App\Console\Commands\SetTelegramWebhook;
use App\Console\Commands\SendTelegramMessage;
use App\Console\Commands\SyncEdoLightDocuments;
use App\Console\Commands\SyncHhResumes;
use App\Console\Commands\SyncTelegramUpdates;
use App\Console\Commands\SyncTinkoffBank;
use App\Console\Commands\UpsertUser;
use App\Http\Middleware\RequireInternalApiToken;
use App\Http\Middleware\RequireHhBrowserCaptureToken;
use App\Http\Middleware\RequireAdminBasicAuth;
use App\Http\Middleware\RequireAdminSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AuthEdoLight::class,
        ImportBankDirectories::class,
        ImportBankStatement::class,
        ImportOzonBankStatement::class,
        RebuildCashLayer::class,
        SetApiCredential::class,
        SetTelegramWebhook::class,
        SendTelegramMessage::class,
        SyncEdoLightDocuments::class,
        SyncHhResumes::class,
        SyncTelegramUpdates::class,
        SyncTinkoffBank::class,
        UpsertUser::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.basic' => RequireAdminBasicAuth::class,
            'admin.session' => RequireAdminSession::class,
            'internal.api' => RequireInternalApiToken::class,
            'hh.browser.capture' => RequireHhBrowserCaptureToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
