<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a validated request ID to response headers and structured log context.
 */
class RequestCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only accept UUIDs from callers; reject arbitrary header content from logs.
        $requestId = $request->header('X-Request-ID');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $startedAt = hrtime(true);

        try {
            $response = $next($request);
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $response->headers->set('X-Request-ID', $requestId);
            $response->headers->set('X-Response-Time-Ms', number_format($durationMs, 2, '.', ''));

            if ($durationMs >= config('performance.slow_request_threshold_ms', 1000)) {
                Log::warning('Slow HTTP request', [
                    'route_name' => $request->route()?->getName() ?? 'unmatched',
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'duration_ms' => round($durationMs, 2),
                    'memory_peak_bytes' => memory_get_peak_usage(true),
                ]);
            }

            return $response;
        } finally {
            // Avoid leaking one request's context into the next long-lived worker request.
            Log::withoutContext();
        }
    }
}
