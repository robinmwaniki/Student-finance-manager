#!/usr/bin/env bash
set -Eeuo pipefail

# This test owns a dedicated ephemeral Redis container and a dynamically
# assigned localhost port. It never stops or reconfigures the CI Redis service.
REDIS_CONTAINER="sfm-redis-recovery-$$"
REDIS_PORT=""
PROBE_KEY="sfm:recovery:probe:$$"
REDIS_IMAGE="${SFM_REDIS_TEST_IMAGE:-redis:7-alpine}"

cleanup() {
    local status=$?
    docker rm -f "$REDIS_CONTAINER" >/dev/null 2>&1 || true
    exit "$status"
}
trap cleanup EXIT

docker run --detach \
    --name "$REDIS_CONTAINER" \
    --publish 127.0.0.1::6379 \
    "$REDIS_IMAGE" \
    redis-server --appendonly yes --appendfsync always >/dev/null

REDIS_PORT="$(docker port "$REDIS_CONTAINER" 6379/tcp | awk -F: 'NR == 1 { print $NF }')"
if [[ -z "$REDIS_PORT" ]]; then
    echo "Could not determine the isolated Redis host port." >&2
    exit 1
fi

export REDIS_CLIENT=phpredis
export REDIS_HOST=127.0.0.1
export REDIS_PORT
export REDIS_DB=0
export REDIS_URL=""

wait_for_redis() {
    for attempt in $(seq 1 30); do
        if docker exec "$REDIS_CONTAINER" redis-cli ping 2>/dev/null | grep -q '^PONG

redis_app_command() {
    php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $redis = Illuminate\Support\Facades\Redis::connection("default");
    $operation = $argv[1];
    $key = $argv[2];
    if ($operation === "write") {
        $redis->set($key, "persisted-through-restart");
        exit(0);
    }
    $value = $redis->get($key);
    if ($value !== "persisted-through-restart") {
        fwrite(STDERR, "Expected persisted Redis probe value; received " . var_export($value, true) . PHP_EOL);
        exit(2);
    }
    echo $value . PHP_EOL;
    ' "$1" "$PROBE_KEY"
}

wait_for_redis
redis_app_command write
echo "PASS: Laravel wrote a probe through its configured phpredis connection."

docker stop "$REDIS_CONTAINER" >/dev/null

# A Redis outage must be visible to the application. Silent success, a false
# value, or an unhandled command result must not be mistaken for recovery.
if php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    Illuminate\Support\Facades\Redis::connection("default")->get($argv[1]);
    fwrite(STDERR, "Redis read unexpectedly succeeded while the isolated server was stopped." . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    if (!preg_match("/connect|connection|refused|timed out/i", $message)) {
        fwrite(STDERR, "Redis failed for an unexpected reason: " . get_class($exception) . ": " . $message . PHP_EOL);
        exit(2);
    }
    echo "EXPECTED OUTAGE: " . get_class($exception) . PHP_EOL;
}
' "$PROBE_KEY"; then
    :
else
    echo "Application did not report the isolated Redis outage as expected." >&2
    exit 1
fi

docker start "$REDIS_CONTAINER" >/dev/null
wait_for_redis
RECOVERED_VALUE="$(redis_app_command read)"
if [[ "$RECOVERED_VALUE" != "persisted-through-restart" ]]; then
    echo "Redis did not return the durable probe value after restart." >&2
    exit 1
fi

echo "PASS: Laravel observed the Redis outage instead of silently succeeding."
echo "PASS: Redis restarted and Laravel read the AOF-persisted probe value."
 \
            && php -r '$socket = @fsockopen("127.0.0.1", (int) getenv("REDIS_PORT"), $errno, $error, 1); if (! $socket) exit(1); fclose($socket);' 2>/dev/null; then
            return 0
        fi
        sleep 1
    done
    echo "Isolated Redis did not become ready through both its container and published host port." >&2
    docker logs "$REDIS_CONTAINER" >&2 || true
    return 1
}

redis_app_command() {
    php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $redis = Illuminate\Support\Facades\Redis::connection("default");
    $operation = $argv[1];
    $key = $argv[2];
    if ($operation === "write") {
        $redis->set($key, "persisted-through-restart");
        exit(0);
    }
    $value = $redis->get($key);
    if ($value !== "persisted-through-restart") {
        fwrite(STDERR, "Expected persisted Redis probe value; received " . var_export($value, true) . PHP_EOL);
        exit(2);
    }
    echo $value . PHP_EOL;
    ' "$1" "$PROBE_KEY"
}

wait_for_redis
redis_app_command write
echo "PASS: Laravel wrote a probe through its configured phpredis connection."

docker stop "$REDIS_CONTAINER" >/dev/null

# A Redis outage must be visible to the application. Silent success, a false
# value, or an unhandled command result must not be mistaken for recovery.
if php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    Illuminate\Support\Facades\Redis::connection("default")->get($argv[1]);
    fwrite(STDERR, "Redis read unexpectedly succeeded while the isolated server was stopped." . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    if (!preg_match("/connect|connection|refused|timed out/i", $message)) {
        fwrite(STDERR, "Redis failed for an unexpected reason: " . get_class($exception) . ": " . $message . PHP_EOL);
        exit(2);
    }
    echo "EXPECTED OUTAGE: " . get_class($exception) . PHP_EOL;
}
' "$PROBE_KEY"; then
    :
else
    echo "Application did not report the isolated Redis outage as expected." >&2
    exit 1
fi

docker start "$REDIS_CONTAINER" >/dev/null
wait_for_redis
RECOVERED_VALUE="$(redis_app_command read)"
if [[ "$RECOVERED_VALUE" != "persisted-through-restart" ]]; then
    echo "Redis did not return the durable probe value after restart." >&2
    exit 1
fi

echo "PASS: Laravel observed the Redis outage instead of silently succeeding."
echo "PASS: Redis restarted and Laravel read the AOF-persisted probe value."
