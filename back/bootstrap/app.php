<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.api' => \App\Http\Middleware\AuthenticateApi::class,
        ]);

        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $fmt = fn (int $code, string $message, mixed $data = null) => [
            'statusCode' => $code,
            'status'     => 'error',
            'timestamp'  => now()->toIso8601String(),
            'message'    => $message,
            'data'       => $data,
        ];

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) use ($fmt) {
            return response()->json($fmt(422, $e->getMessage(), $e->errors()), 422);
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) use ($fmt) {
            return response()->json($fmt(404, 'Не найдено'), 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e) use ($fmt) {
            return response()->json($fmt($e->getStatusCode(), $e->getMessage() ?: 'Ошибка'), $e->getStatusCode());
        });
    })->create();
