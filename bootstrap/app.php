<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureLegalTermsAccepted;
use App\Http\Middleware\EnsureSalonMember;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\KeepFlashedData;
use App\Http\Middleware\ResolveCurrentSalon;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleAccountEndpoints;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'salon' => EnsureSalonMember::class,
            'lookup' => KeepFlashedData::class,
            'terms' => EnsureLegalTermsAccepted::class,
        ]);

        // The current salon must be known before route model binding runs,
        // otherwise BelongsToSalon cannot filter `{invitation}` & co.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveCurrentSalon::class,
        );

        // Only the configured domain is trusted (Host header poisoning).
        // Laravel skips this check in local and in tests.
        $middleware->trustHosts(
            at: fn () => [(string) parse_url((string) config('app.url'), PHP_URL_HOST)],
            subdomains: false,
        );

        // The API always answers JSON (also "verify your email" and "sign
        // in"), whatever the Accept header says.
        $middleware->api(prepend: [SecurityHeaders::class, ForceJsonResponse::class]);

        $middleware->web(append: [
            SecurityHeaders::class,
            ThrottleAccountEndpoints::class,
            HandleAppearance::class,
            ResolveCurrentSalon::class,
            HandleInertiaRequests::class,
            // Inside Inertia's middleware, so a refused PUT/DELETE gets a 303.
            EnsureLegalTermsAccepted::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Health data refused by validation must not linger in the session.
        $exceptions->dontFlash(['care_notes', 'document_number', 'consumer_address', 'phone', 'email', 'detail', 'request']);

        // Business rule violations go back to the form as a toast, not a 500.
        $exceptions->render(function (BusinessRuleException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        });

        // A bare "403" line is a dead end: say it with a way back.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $isPage = ! $request->expectsJson() && ! $request->is('api/*', 'plataforma', 'plataforma/*', 'livewire*') && ! $request->hasHeader('X-Livewire');

            if (! $isPage || ! in_array($response->getStatusCode(), [403, 404], true)) {
                return $response;
            }

            return Inertia::render('public/error', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
