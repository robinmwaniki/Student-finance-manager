(() => {
    const MAX_AUTOMATIC_ATTEMPTS = 8;
    const BASE_DELAY_MS = 5000;
    const MAX_DELAY_MS = 300000;

    const retryDelayMs = attempts => Math.min(
        MAX_DELAY_MS,
        BASE_DELAY_MS * (2 ** Math.max(0, Number(attempts || 1) - 1)),
    );

    const shouldRetryAutomatically = (attempts, status, networkFailure = false) => {
        const transientFailure = networkFailure
            || status === 408
            || status === 429
            || (Number(status) >= 500 && Number(status) <= 599);

        return transientFailure && Number(attempts) < MAX_AUTOMATIC_ATTEMPTS;
    };

    const retryPolicy = Object.freeze({
        MAX_AUTOMATIC_ATTEMPTS,
        BASE_DELAY_MS,
        MAX_DELAY_MS,
        retryDelayMs,
        shouldRetryAutomatically,
    });

    if (typeof module !== 'undefined' && module.exports) module.exports = retryPolicy;
    if (typeof window !== 'undefined') window.OfflineRetryPolicy = retryPolicy;
})();
