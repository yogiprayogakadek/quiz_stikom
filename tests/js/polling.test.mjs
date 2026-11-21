import test from 'node:test';
import assert from 'node:assert/strict';
import { createPollingLoop } from '../../resources/js/polling.js';

test('polling does not overlap and backs off after failure', async () => {
    const timers = [];
    let release;
    let calls = 0;
    const loop = createPollingLoop(async () => {
        calls++;
        await new Promise((resolve) => { release = resolve; });
        return calls > 1;
    }, {
        interval: 5000,
        maxInterval: 15000,
        jitter: 0,
        setTimer: (callback, delay) => {
            timers.push({ callback, delay });
            return timers.length;
        },
        clearTimer: () => {},
        random: () => 0,
    });

    loop.start();
    const initial = timers.shift();
    assert.equal(initial.delay, 0);

    const running = initial.callback();
    loop.now();
    const overlapping = timers.shift()?.callback;
    await overlapping?.();
    assert.equal(calls, 1);

    release();
    await running;
    assert.equal(timers.at(-1).delay, 10000);

    const second = timers.pop().callback();
    release();
    await second;
    assert.equal(timers.at(-1).delay, 5000);
});
