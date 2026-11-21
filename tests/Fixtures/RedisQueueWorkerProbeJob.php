<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RedisQueueWorkerProbeJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $resultPath,
        public readonly string $marker,
    ) {}

    public function handle(): void
    {
        file_put_contents($this->resultPath, $this->marker.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
