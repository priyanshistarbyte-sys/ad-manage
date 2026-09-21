<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.pin' => \App\Http\Middleware\EnsureAuthenticated::class,
            'admin'    => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // PIN auth guards every POST endpoint; the login form itself posts
        // before a session exists, so exempt it from CSRF (matches the port).
        $middleware->validateCsrfTokens(except: [
            'login',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
