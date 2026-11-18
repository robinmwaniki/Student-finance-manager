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

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            // Avoid leaking one request's context into the next long-lived worker request.
            Log::withoutContext();
        }
    }
}
