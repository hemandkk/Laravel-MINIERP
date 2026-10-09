<?php

use App\Exceptions\DomainRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$wantsJson = static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) use ($wantsJson): void {
        $exceptions->shouldRenderJsonWhen($wantsJson);

        // Business-rule violations are expected outcomes, not application errors.
        $exceptions->dontReport([DomainRuleException::class]);

        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (DomainRuleException $e, Request $request) use ($wantsJson) {
            if ($wantsJson($request)) {
                return response()->json(['message' => $e->getMessage()], $e->status());
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            $message = $e->getPrevious() instanceof ModelNotFoundException
                ? 'Resource not found.'
                : 'The requested endpoint does not exist.';

            return response()->json(['message' => $message], 404);
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            // MySQL 1205 = lock wait timeout, 1213 = deadlock: the client may safely retry.
            if (in_array($e->errorInfo[1] ?? null, [1205, 1213], true)) {
                return response()->json([
                    'message' => 'The resource is currently being modified by another request. Please retry.',
                ], 409);
            }

            return response()->json([
                'message' => 'A database error occurred while processing the request.',
            ], 500);
        });
    })->create();
