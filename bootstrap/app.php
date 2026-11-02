<?php

use App\Http\Middleware\DeuxFacteurs;
use App\Http\Middleware\ExigerRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class, \App\Http\Middleware\EntetesSecurite::class]);
        $middleware->prepend(\App\Http\Middleware\AnciennesAdressesV1::class);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->alias(['role' => ExigerRole::class, 'deux_facteurs' => DeuxFacteurs::class, 'hors_demo' => \App\Http\Middleware\BloquerSurDemo::class]);
        $middleware->redirectGuestsTo(fn () => route('connexion'));
        $middleware->redirectUsersTo(fn () => route('admin.tableau'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
