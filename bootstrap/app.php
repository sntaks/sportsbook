<?php

use App\Betting\BetNotPlaced;
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
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A refused bet is the player's to fix, not an error to report.
        $exceptions->dontReport(BetNotPlaced::class);

        $exceptions->render(fn (BetNotPlaced $exception, Request $request) => $request->is('api/*')
            ? response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422)
            : null);
    })->create();
