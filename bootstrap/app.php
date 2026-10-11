<?php

use App\Demo\DemoAccountCannotChange;
use App\Http\Middleware\AddResponseHeaders;
use App\Support\Alert;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddResponseHeaders::class);
        $middleware->validateCsrfTokens(except: ['payments/confirmation/*']);

        // A visitor who needs to be signed in is sent to the shop's sign-in page, and a
        // signed-in customer who opens it is sent to their account. The back office
        // sends its own staff to its own sign-in page.
        $middleware->redirectGuestsTo(fn (Request $request) => route('account.login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('account.show'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Whatever is worth writing in the log as an error is worth telling somebody about.
        $exceptions->report(fn (Throwable $problem) => Alert::forException($problem));

        // A demonstration account that tries to change what customers see is told why not, in plain words.
        $exceptions->dontReport(DemoAccountCannotChange::class);
        $exceptions->render(fn (DemoAccountCannotChange $refused) => response()->view('errors.demo', ['message' => $refused->getMessage()], 403));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
