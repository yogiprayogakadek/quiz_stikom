const root = document.querySelector("[data-quiz-machine-root]");

if (root) {
    const dataNode = root.querySelector("[data-quiz-machine-data]");
    const data = dataNode ? JSON.parse(dataNode.textContent) : null;

    if (data) {
        const svg = root.querySelector("svg");
        const carriage = svg.querySelector("[data-carriage]");
        const plug = svg.querySelector("[data-plug]");
        const card = svg.querySelector("[data-card]");
        const gauge = svg.querySelector("[data-gauge]");
        const print = svg.querySelector("[data-print]");
        const plunger = svg.querySelector("[data-plunger]");
        const needle = svg.querySelector("[data-needle]");
        const striker = svg.querySelector("[data-bell-striker]");
        const cablePath = svg.querySelector("[data-cable]");
        const cableEnd = svg.querySelector("[data-cable-end]");
        const line1 = svg.querySelector('[data-screen-line="1"]');
        const line2 = svg.querySelector('[data-screen-line="2"]');
        const readout = document.querySelector("[data-quiz-readout]");
        const readoutText = readout?.querySelector("[data-readout-text]");
        const channelNodes = [...svg.querySelectorAll("[data-channel]")];
        const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");

        const initialNode = document.getElementById("kuis-initial");
        let initial = null;
        try {
            initial = initialNode ? JSON.parse(initialNode.textContent) : null;
        } catch {
            initial = null;
        }

        const channels = data.channels;
        const originX = data.originX;
        const startX = channels[0].x;

        let state = "idle";
        let results = Array.isArray(initial?.results) ? initial.results : [];
        let active = 0;
        let targetX = startX;
        let x = startX;
        let targetProgress = 1;
        let progress = 1;
        let timer = 0;
        let printPhase = 0;
        let tourIndex = 0;
        let tourClock = 0;
        let lastChannel = -1;
        let lastScreen = "";
        let visible = true;
        let frame = 0;
        let lastTime = 0;
        let ringAt = -1e9;
        let lastRingChannel = -1;

        function activeChannel() {
            if (state === "results") return active;
            if (state === "idle") return tourIndex;
            return -1;
        }

        function truncate(text, max) {
            const value = String(text ?? "").trim();
            if (!value) return "TANPA NAMA";
            return value.length > max ? `${value.slice(0, max - 1)}…` : value;
        }

        function screenText() {
            const channel = activeChannel();
            const label = channel >= 0
                ? `SALURAN ${String(channel + 1).padStart(2, "0")}`
                : "";

            if (state === "loading") {
                return { first: "MENCARI", second: "ORGANISASI" };
            }
            if (state === "empty") {
                return { first: "TIDAK ADA", second: "SALURAN" };
            }
            if (state === "error") {
                return { first: "PENCARIAN", second: "GAGAL" };
            }
            if (state === "results") {
                return {
                    first: label,
                    second: truncate(results[active]?.name, data.screen.max),
                };
            }
            return { first: label || "SALURAN 01", second: "PRATINJAU" };
        }

        function readoutCopy() {
            const channel = activeChannel();
            const label = channel >= 0
                ? `Saluran ${String(channel + 1).padStart(2, "0")}`
                : "Saluran";
            if (state === "loading") return "Mencari organisasi...";
            if (state === "empty") return "Tidak ada organisasi aktif yang cocok.";
            if (state === "error") return "Pencarian gagal. Coba lagi.";
            if (state === "results") {
                const name = results[active]?.name ?? "Organisasi";
                return `${label} · ${name}`;
            }
            return "Pratinjau alur. Pilih organisasi lewat pencarian.";
        }

        function setChannelNodes(channel) {
            for (const node of channelNodes) {
                const index = Number(node.getAttribute("data-channel"));
                node.toggleAttribute("data-on", index === channel);
            }
        }

        function refreshCopy() {
            const copy = screenText();
            const key = `${state}|${activeChannel()}|${copy.first}|${copy.second}`;
            if (key === lastScreen) return;
            lastScreen = key;
            if (line1) line1.textContent = copy.first;
            if (line2) line2.textContent = copy.second;
            if (readoutText) readoutText.textContent = readoutCopy();
            const channel = activeChannel();
            if (channel !== lastChannel) {
                lastChannel = channel;
                setChannelNodes(channel);
            }
            root.dataset.participants = state === "results" ? "on" : "off";
        }

        function ring() {
            if (reduced.matches) return;
            ringAt = performance.now();
        }

        function setState(next) {
            state = next;
            root.dataset.state = state;
            targetProgress = state === "results" || state === "idle" ? 1 : 0;
            if (state === "idle") {
                tourClock = 0;
                tourIndex = 0;
                targetX = channels[0].x;
            }
            if (state === "results") {
                targetX = channels[active]?.x ?? channels[0].x;
            }
            if (state === "empty" || state === "error" || state === "loading") {
                targetProgress = 0;
            }
            refreshCopy();
            start();
        }

        function applySearch(detail) {
            const next = detail?.state ?? "idle";
            results = Array.isArray(detail?.results) ? detail.results : [];
            active = Math.max(0, Math.min(results.length - 1, Number(detail?.active) || 0));
            if (next === "results" && active !== lastRingChannel) {
                lastRingChannel = active;
                ring();
            }
            setState(next);
        }

        function needlePath() {
            const needleData = data.needle;
            const angle = ((-135 + timer * 270) * Math.PI) / 180;
            const dx = Math.sin(angle);
            const dy = -Math.cos(angle);
            const [px, py] = needleData.pivot;
            const tip = [
                px + (dx * needleData.radius * needleData.axisX[0]) + (dy * needleData.radius * needleData.axisY[0]),
                py + (dx * needleData.radius * needleData.axisX[1]) + (dy * needleData.radius * needleData.axisY[1]),
            ];
            const back = [
                px - (dx * needleData.tail * needleData.axisX[0]) - (dy * needleData.tail * needleData.axisY[0]),
                py - (dx * needleData.tail * needleData.axisX[1]) - (dy * needleData.tail * needleData.axisY[1]),
            ];
            return `M${back[0].toFixed(2)} ${back[1].toFixed(2)}L${tip[0].toFixed(2)} ${tip[1].toFixed(2)}`;
        }

        function draw(now) {
            refreshCopy();

            const dx = (x - originX) * data.carriage.axis[0];
            const dy = (x - originX) * data.carriage.axis[1];
            if (carriage) {
                carriage.setAttribute(
                    "transform",
                    `translate(${(data.carriage.base[0] + dx).toFixed(2)} ${(data.carriage.base[1] + dy).toFixed(2)})`,
                );
            }

            if (plug) {
                const retract = data.plug.retract * (1 - progress);
                plug.setAttribute(
                    "transform",
                    `translate(${(data.yAxis[0] * retract).toFixed(2)} ${(data.yAxis[1] * retract).toFixed(2)})`,
                );
            }

            if (card) {
                const travel = data.card.travel * progress;
                card.setAttribute(
                    "transform",
                    `translate(${(data.yAxis[0] * travel).toFixed(2)} ${(data.yAxis[1] * travel).toFixed(2)})`,
                );
            }

            if (gauge) {
                const travel = data.gauge.travel * progress;
                gauge.setAttribute(
                    "transform",
                    `translate(${(data.zAxis[0] * travel).toFixed(2)} ${(data.zAxis[1] * travel).toFixed(2)})`,
                );
            }

            if (plunger) {
                const lift = 1.6 * (1 - progress);
                plunger.setAttribute(
                    "transform",
                    `translate(${(data.zAxis[0] * lift).toFixed(2)} ${(data.zAxis[1] * lift).toFixed(2)})`,
                );
            }

            if (print) {
                const travel = data.print.travel * progress * (0.5 + 0.5 * Math.sin(printPhase));
                print.setAttribute(
                    "transform",
                    `translate(${(data.yAxis[0] * travel).toFixed(2)} ${(data.yAxis[1] * travel).toFixed(2)})`,
                );
            }

            if (needle) {
                needle.setAttribute("d", needlePath());
            }

            if (striker) {
                const elapsed = Math.max(0, (now - ringAt) / 1000);
                const angle = elapsed < 1.2
                    ? Math.sin(elapsed * 30) * data.bell.angle * Math.exp(-elapsed * 6)
                    : 0;
                striker.setAttribute(
                    "transform",
                    `rotate(${angle.toFixed(2)} ${data.bell.pivot[0].toFixed(2)} ${data.bell.pivot[1].toFixed(2)})`,
                );
            }

            if (cablePath) {
                const guide = data.cable.guide;
                const corner = [
                    data.cable.cornerBase[0] + dx,
                    data.cable.cornerBase[1] + dy,
                ];
                const end = [
                    data.cable.endBase[0] + dx,
                    data.cable.endBase[1] + dy,
                ];
                const mid = [
                    (guide[0] + corner[0]) / 2,
                    (guide[1] + corner[1]) / 2 + data.cable.sag,
                ];
                const path = `M${guide[0].toFixed(1)} ${guide[1].toFixed(1)} Q${mid[0].toFixed(1)} ${mid[1].toFixed(1)} ${corner[0].toFixed(1)} ${corner[1].toFixed(1)} L${end[0].toFixed(1)} ${end[1].toFixed(1)}`;
                cablePath.setAttribute("d", path);
                const shadow = svg.querySelector(".quiz-cable-shadow");
                if (shadow) shadow.setAttribute("d", path);
                if (cableEnd) {
                    cableEnd.setAttribute("cx", end[0].toFixed(1));
                    cableEnd.setAttribute("cy", end[1].toFixed(1));
                }
            }
        }

        function tick(now) {
            frame = 0;
            if (!visible) return;
            const delta = lastTime ? Math.min((now - lastTime) / 1000, 1 / 30) : 1 / 60;
            lastTime = now;

            if (state === "idle" && !reduced.matches) {
                tourClock += delta;
                const nextIndex = Math.floor(tourClock / 2.6) % channels.length;
                if (nextIndex !== tourIndex) {
                    tourIndex = nextIndex;
                    if (state === "idle" && tourIndex !== lastRingChannel) {
                        lastRingChannel = tourIndex;
                        ring();
                    }
                }
                targetX = channels[tourIndex].x;
            }

            const follow = 1 - Math.exp(-delta / 0.34);
            x += (targetX - x) * follow;
            progress += (targetProgress - progress) * (1 - Math.exp(-delta / 0.22));

            if (targetProgress > 0.5) {
                timer = 0.5 + 0.5 * Math.sin(now / 2600 - Math.PI / 2);
                printPhase += delta * 4.2;
            } else {
                timer += (0 - timer) * (1 - Math.exp(-delta / 0.3));
            }

            draw(now);
            frame = requestAnimationFrame(tick);
        }

        function start() {
            if (reduced.matches || frame) return;
            lastTime = 0;
            frame = requestAnimationFrame(tick);
        }

        function stop() {
            if (frame) {
                cancelAnimationFrame(frame);
                frame = 0;
            }
            lastTime = 0;
        }

        function settle() {
            if (!reduced.matches) return;
            x = targetX;
            progress = targetProgress;
            timer = targetProgress > 0.5 ? 0.35 : 0;
            printPhase = 0.8;
            ringAt = -1e9;
            draw(performance.now());
        }

        const observer = new IntersectionObserver(
            (entries) => {
                visible = entries.some((entry) => entry.isIntersecting);
                if (visible) {
                    start();
                } else {
                    stop();
                }
            },
            { rootMargin: "120px 0px" },
        );

        observer.observe(root);

        const onReducedChange = () => {
            if (reduced.matches) {
                stop();
                settle();
            } else {
                start();
            }
        };

        if (reduced.addEventListener) {
            reduced.addEventListener("change", onReducedChange);
        } else if (reduced.addListener) {
            reduced.addListener(onReducedChange);
        }

        window.addEventListener("kuis:search", (event) => {
            applySearch(event.detail);
            if (reduced.matches) settle();
        });

        if (initial?.q && results.length) {
            active = 0;
            lastRingChannel = -1;
            setState("results");
        } else if (initial?.q) {
            setState("empty");
        } else {
            setState("idle");
        }

        if (reduced.matches) {
            settle();
        } else {
            draw(performance.now());
            start();
        }
    }
}
