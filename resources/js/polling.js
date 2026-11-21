export function createPollingLoop(task, options = {}) {
    const interval = Math.max(100, Number(options.interval ?? 5000));
    const maxInterval = Math.max(interval, Number(options.maxInterval ?? 15000));
    const jitter = Math.max(0, Number(options.jitter ?? 1000));
    const setTimer = options.setTimer ?? globalThis.setTimeout;
    const clearTimer = options.clearTimer ?? globalThis.clearTimeout;
    const random = options.random ?? Math.random;
    let timer = null;
    let running = false;
    let failures = 0;
    let stopped = true;

    const schedule = (delay) => {
        if (stopped) return;
        if (timer !== null) clearTimer(timer);
        timer = setTimer(run, delay + Math.floor(random() * jitter));
    };

    const run = async () => {
        timer = null;
        if (stopped || running) return;
        running = true;
        let succeeded = false;

        try {
            succeeded = (await task()) !== false;
        } catch {
            succeeded = false;
        } finally {
            running = false;
            if (stopped) return;
            failures = succeeded ? 0 : failures + 1;
            schedule(succeeded ? interval : Math.min(maxInterval, interval * (2 ** failures)));
        }
    };

    return {
        start() {
            stopped = false;
            schedule(0);
        },
        now() {
            if (!stopped) schedule(0);
        },
        stop() {
            stopped = true;
            if (timer !== null) clearTimer(timer);
            timer = null;
        },
        get running() {
            return running;
        },
    };
}
