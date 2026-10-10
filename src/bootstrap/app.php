<?php

use App\Modules\Interview\Application\InterviewUseCaseException;
use Illuminate\Console\Scheduling\Schedule;
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
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('learning:cleanup-exercise-audio')->hourly();
        $schedule->command('ai:cleanup-voice-recordings')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'ai.rate_limit' => \App\Http\Middleware\AiRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InterviewUseCaseException $exception, Request $request) {
            $status = match ($exception->reason) {
                InterviewUseCaseException::NOT_FOUND => 404,
                InterviewUseCaseException::CONFLICT => 409,
                default => 422,
            };

            return response()->json(['message' => $exception->getMessage()], $status);
        });
    })->create();
