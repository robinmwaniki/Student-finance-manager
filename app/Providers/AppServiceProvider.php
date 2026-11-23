<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $threshold = (float) config('performance.slow_database_query_threshold_ms', 500);

            if ($query->time < $threshold) {
                return;
            }

            $request = app()->bound('request') ? app('request') : null;

            // Deliberately omit SQL text and binding values: financial data can appear in either.
            Log::warning('Slow database query', [
                'connection' => $query->connectionName,
                'duration_ms' => round($query->time, 2),
                'bindings_count' => count($query->bindings),
                'route_name' => $request?->route()?->getName() ?? 'unmatched',
                'request_id' => $request?->attributes->get('request_id'),
            ]);
        });
    }
}
