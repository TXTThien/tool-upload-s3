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
    ->withMiddleware(function (Middleware $middleware): void {
        // The CMS SSO cookie is a raw JWT from another service, not one
        // Laravel encrypted itself — exclude it so EncryptCookies doesn't
        // fail to decrypt it and silently strip it from the request.
        $middleware->encryptCookies(except: [
            env('CMS_AUTH_COOKIE', 'octokit_auth_token'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
