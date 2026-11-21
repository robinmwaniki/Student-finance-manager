<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class QueueWorkerLifecycleProbe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 0;

    public function __construct(
        public readonly string $attemptsPath,
        public readonly string $successPath,
        public readonly bool $failFirstAttempt = false,
        public readonly ?string $startedPath = null,
        public readonly int $holdFirstAttemptSeconds = 0,
    ) {}

    public function handle(): void
    {
        $attemptsFile = fopen($this->attemptsPath, 'c+');

        if ($attemptsFile === false) {
            throw new RuntimeException('Unable to open the queue probe attempts file.');
        }

        try {
            if (! flock($attemptsFile, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the queue probe attempts file.');
            }

            $contents = stream_get_contents($attemptsFile);
            $attempt = ((int) trim($contents === false ? '' : $contents)) + 1;

            rewind($attemptsFile);
            ftruncate($attemptsFile, 0);
            fwrite($attemptsFile, (string) $attempt);
            fflush($attemptsFile);
            flock($attemptsFile, LOCK_UN);
        } finally {
            fclose($attemptsFile);
        }

        if ($attempt === 1 && $this->startedPath !== null) {
            file_put_contents($this->startedPath, 'started');
        }

        if ($attempt === 1 && $this->holdFirstAttemptSeconds > 0) {
            sleep($this->holdFirstAttemptSeconds);
        }

        if ($this->failFirstAttempt && $attempt === 1) {
            throw new RuntimeException('Intentional first-attempt failure for queue lifecycle testing.');
        }

        file_put_contents($this->successPath, "executed\n", FILE_APPEND | LOCK_EX);
    }
}
