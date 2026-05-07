<?php

use App\Exceptions\BackofficeApiException;
use App\Http\Middleware\BackofficeAuth;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'backoffice.auth' => BackofficeAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $findApiException = function (Throwable $e): ?BackofficeApiException {
            do {
                if ($e instanceof BackofficeApiException) {
                    return $e;
                }

                $e = $e->getPrevious();
            } while ($e);

            return null;
        };

        $exceptions->report(function (Throwable $e) use ($findApiException) {
            return $findApiException($e) ? false : null;
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($findApiException) {
            $apiException = $findApiException($e);

            if (! $apiException) {
                return null;
            }

            $isLivewire = $request->hasHeader('X-Livewire')
                || str_starts_with((string) $request->path(), 'livewire/');

            if ($apiException->status === 401) {
                session()->forget(['bo_user', 'bo_access_token', 'bo_refresh_token', 'bo_token_expires_at']);

                if ($isLivewire || $request->expectsJson()) {
                    return response()->json([
                        'message' => $apiException->userMessage(),
                        'redirect' => route('login'),
                    ], 401);
                }

                return redirect()->route('login')->withErrors(['email' => $apiException->userMessage()]);
            }

            $payload = [
                'message' => $apiException->userMessage(),
                'code' => $apiException->errorCode,
                'status' => $apiException->status,
                'details' => $apiException->details,
                'correlationId' => $apiException->correlationId,
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, $apiException->status ?: 500);
            }

            $status = $apiException->status >= 400 && $apiException->status < 600 ? $apiException->status : 500;

            return response()->view('errors.api', $payload, $status);
        });
    })->create();
