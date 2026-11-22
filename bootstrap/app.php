<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Lets Auth::logoutOtherDevices() end other sessions after a password change.
        $middleware->web(append: [AuthenticateSession::class]);

        // Render sits behind a proxy. Trusting the proxy that calls the app gives each visitor their own IP for rate limits.
        // X-Forwarded-Host is not trusted: a client could set it and get password reset links pointing at another site.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
