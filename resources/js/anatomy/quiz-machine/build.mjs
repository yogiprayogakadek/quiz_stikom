// Generates views/partials/landing/quiz-machine.blade.php from the local isometric kit.
// Run: node resources/js/anatomy/quiz-machine/build.mjs
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import * as k from "../kit/iso-kit.mjs";

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, "..", "..", "..");
const WIDTH = 940;
const HEIGHT = 740;
const PAD = 22;

/* ---------------------------------------------------------------- geometry */

const PLATE = { x: -170, y: -70, w: 340, d: 140, r: 12 };
const PLATE_H = 10;
const FOOT_H = 6;

const PANEL = { x: -140, y: -60, w: 280, d: 14, r: 4 };
const PANEL_FRONT = -46;
const PANEL_Z = 0;
const PANEL_H = 150;

const POST_L = { x: -152, y: -60, w: 12, d: 14, r: 2 };
const POST_R = { x: 140, y: -60, w: 12, d: 14, r: 2 };
const POST_H = 148;

const BEAM = { x: -152, y: -60, w: 304, d: 14, r: 3 };
const BEAM_Z = 148;
const BEAM_H = 14;

const BAY_W = 38;
const BAY_GAP = 5.6;
const BAY_Z = 18;
const BAY_H = 60;
const BAY_FRONT = -40;
const BAYS = Array.from({ length: 6 }, (_, i) => {
    const x0 = -128 + i * (BAY_W + BAY_GAP);
    return { x0, x1: x0 + BAY_W, cx: x0 + BAY_W / 2, n: i };
});

const SCREEN = { x0: -96, x1: 96, z0: 86, z1: 144, r: 6, depth: 3 };
const SCREEN_IN = { x0: -88, x1: 88, z0: 94, z1: 136, r: 4, depth: 4 };

const CAR = { x: -38, y: -30, w: 76, d: 34, r: 6 };
const CAR_Z = 6;
const CAR_H = 8;
const CONSOLE = { x: -30, y: -24, w: 60, d: 20, r: 4 };
const CONSOLE_Z = 14;
const CONSOLE_H = 14;
const DASH = { x: -34, y: 3, w: 68, d: 2, r: 2 };
const DASH_Z = 6;
const DASH_H = 18;

const DIAL = { x: 8, y: -16, r: 10, z: 28, h: 6 };
const DIAL_TOP = DIAL.z + DIAL.h;

const FRONT = { x: -132, y: 40, w: 264, d: 24, r: 4 };
const FRONT_Z = 0;
const FRONT_H = 4;

const RAIL_Y = [-34, 2];
const RAIL_Z = 0;
const RAIL_H = 4;

const GUIDE = [-146, -42, 82];
const CABLE_CORNER = [-38, -42, 82];
const CABLE_END = [-38, -20, 30];

const BELL = { x: 110, y: -53 };
const BELL_PIVOT = [98, -45, 166];
const REEL = { x: -110, y: -53 };
const REEL_GUIDE = [-110, -42, 166];

/* --------------------------------------------------------------- projection */

const FIT = k.boxCorners({ x: -182, y: -82, w: 364, d: 164 }, -18, 182);
const P = k.fitProjection(FIT, WIDTH, HEIGHT, {
    pad: PAD,
    azimuth: 45,
    elevation: 32,
});

const at = (point) => k.iso(point, P);
const S = (paths, style) => k.solidSvg(paths, style);
const L = (d, style) => k.lineSvg(d, style);
const D = (points, style) => k.dotsSvg(points, style);
const planeOf = (y) => k.sideMatrix([0, y], 0, P, "left");
const axis = (v) => k.axisVector(P, v);

const flat = (x0, z0, x1, z1, r, steps = 6) =>
    k.pathOf(
        k
            .roundedPlan({ x: x0, y: z0, w: x1 - x0, d: z1 - z0, r }, steps)
            .map(([u, z]) => [u, -z]),
        true,
    );

const frontLine = (x0, z0, x1, z1, y = PANEL_FRONT) =>
    k.pathOf([at([x0, y, z0]), at([x1, y, z1])]);

const frontPath = (points, y = PANEL_FRONT, closed = false) =>
    k.pathOf(points.map(([x, z]) => at([x, y, z])), closed);

const sideRect = (x, y0, z0, y1, z1) =>
    k.pathOf(
        [
            at([x, y0, z0]),
            at([x, y1, z0]),
            at([x, y1, z1]),
            at([x, y0, z1]),
        ],
        true,
    );

const topRect = (x0, y0, x1, y1, z) =>
    k.pathOf(
        [
            at([x0, y0, z]),
            at([x1, y0, z]),
            at([x1, y1, z]),
            at([x0, y1, z]),
        ],
        true,
    );

/* -------------------------------------------------------------------- parts */

function base() {
    const out = [];
    out.push(
        `<path class="iso-halo" d="${k.haloOf(PLATE, -PLATE_H - FOOT_H, PLATE_H, P)}"/>`,
    );

    for (const [x, y] of [
        [PLATE.x + 22, PLATE.y + 20],
        [PLATE.x + PLATE.w - 22, PLATE.y + 20],
        [PLATE.x + 22, PLATE.y + PLATE.d - 20],
        [PLATE.x + PLATE.w - 22, PLATE.y + PLATE.d - 20],
    ]) {
        out.push(
            S(k.cylinder(x, y, 7, -PLATE_H - FOOT_H, 1.6, P, 24), {
                tone: "lo",
                crease: "none",
            }),
        );
        out.push(
            S(
                k.cylinder(
                    x,
                    y,
                    5.4,
                    -PLATE_H - FOOT_H + 1.6,
                    FOOT_H - 1.6,
                    P,
                    24,
                ),
                { tone: "lo", crease: "none" },
            ),
        );
    }

    out.push(S(k.slabOf(PLATE, -PLATE_H, PLATE_H, P, 8, 1.6), { tone: "mid" }));
    out.push(L(k.planOutline(k.insetPlan(PLATE, 6), 0, P), { tone: "lo" }));
    out.push(
        L(k.planOutline(k.insetPlan(PLATE, 13), 0.06, P), { tone: "faint" }),
    );

    for (let x = -150; x <= 150; x += 30) {
        out.push(
            L(
                k.lineOnTop(
                    [x, PLATE.y + 8],
                    [x, PLATE.y + 15],
                    0.08,
                    P,
                ),
                { tone: "faint" },
            ),
        );
    }

    for (let x = -160; x <= 160; x += 20) {
        out.push(
            D([at([x, PLATE.y + PLATE.d - 14, 0.1])], {
                size: 0.34,
                tone: "faint",
            }),
        );
    }

    for (const x of [-160, 160]) {
        for (const y of [-58, 58]) {
            out.push(
                D([at([x, y, 0.12])], { size: 0.5, tone: "lo" }),
            );
        }
    }

    return out.join("");
}

function ledgerBar() {
    const out = [];
    out.push(
        L(frontLine(-168, -4, 168, -4, PLATE.y + PLATE.d), { tone: "faint" }),
    );
    out.push(
        L(frontLine(-168, -7, 168, -7, PLATE.y + PLATE.d), { tone: "faint" }),
    );
    for (const x of [-158, 158]) {
        out.push(
            D([at([x, PLATE.y + PLATE.d + 0.1, -5.5])], {
                size: 0.55,
                tone: "lo",
            }),
        );
    }
    return out.join("");
}

function backFrame() {
    const out = [];
    out.push(
        S(k.slabOf(PANEL, PANEL_Z, PANEL_H, P, 4, 0.6), { tone: "mid" }),
    );

    const border = flat(-134, 6, 134, 144, 4);
    out.push(
        `<g transform="${planeOf(PANEL_FRONT)}"><path class="iso-line" data-tone="faint" d="${border}"/></g>`,
    );

    for (let i = 0; i < 12; i++) {
        const x = -120 + i * 22;
        out.push(
            `<g transform="${planeOf(PANEL_FRONT)}"><path class="quiz-vent" d="${flat(x, 8, x + 8, 14, 1.5)}"/></g>`,
        );
    }

    out.push(
        L(frontLine(-132, 82, 132, 82), { tone: "faint" }),
    );
    out.push(
        L(frontLine(-132, 8, -132, 144), { tone: "faint" }),
    );
    out.push(
        L(frontLine(132, 8, 132, 144), { tone: "faint" }),
    );

    for (const [x, z] of [
        [-136, 10],
        [136, 10],
        [-136, 142],
        [136, 142],
    ]) {
        out.push(D([at([x, PANEL_FRONT, z])], { size: 0.55, tone: "lo" }));
        out.push(
            L(
                k.pathOf(
                    [
                        [x - 0.8, -46, z],
                        [x + 0.8, -46, z],
                    ].map((p) => at(p)),
                ),
                { tone: "faint" },
            ),
        );
    }

    for (const post of [POST_L, POST_R]) {
        out.push(
            S(k.slabOf(post, 0, POST_H, P, 3, 0.8), { tone: "mid" }),
        );
    }

    for (const x of [-146, 146]) {
        out.push(
            `<g transform="${planeOf(PANEL_FRONT)}"><path class="quiz-vent" d="${flat(x - 1.1, 12, x + 1.1, 136, 1)}"/></g>`,
        );
        for (const z of [22, 130]) {
            out.push(
                D([at([x, PANEL_FRONT, z])], { size: 0.5, tone: "lo" }),
            );
        }
    }

    for (const x of [-152, 140]) {
        out.push(
            S(
                k.slabOf(
                    { x: x - 4, y: -52, w: 20, d: 11, r: 2 },
                    0,
                    9,
                    P,
                    3,
                    0.6,
                ),
                { tone: "mid" },
            ),
        );
    }

    out.push(
        S(k.slabOf(BEAM, BEAM_Z, BEAM_H, P, 4, 1.2), { tone: "mid" }),
    );
    out.push(L(frontLine(-146, 155, 146, 155, PANEL_FRONT), { tone: "faint" }));
    for (const x of [-120, -80, 80, 120]) {
        out.push(
            D([at([x, PANEL_FRONT, 158])], { size: 0.5, tone: "lo" }),
        );
    }

    out.push(
        L(
            k.lineOnTop(
                [-130, -53],
                [130, -53],
                BEAM_Z + BEAM_H + 0.08,
                P,
            ),
            { tone: "faint" },
        ),
    );

    return out.join("");
}

function bays() {
    const out = [];
    for (const bay of BAYS) {
        out.push(
            S(
                k.slabOf(
                    { x: bay.x0, y: -46, w: BAY_W, d: 6, r: 4 },
                    BAY_Z,
                    BAY_H,
                    P,
                    5,
                    0.7,
                ),
                { tone: "mid" },
            ),
        );

        out.push(
            `<g transform="${planeOf(BAY_FRONT)}"><path class="quiz-slot" d="${flat(bay.x0 + 6, 62, bay.x1 - 6, 74, 2)}"/><path class="iso-line" data-tone="faint" d="${flat(bay.x0 + 6, 62, bay.x1 - 6, 74, 2)}"/></g>`,
        );

        out.push(
            `<g class="quiz-lamp" data-channel="${bay.n}" transform="${planeOf(BAY_FRONT)}">
                <circle class="lamp-glow" cx="${bay.cx}" cy="-54" r="7"/>
                <circle class="lamp-ring" cx="${bay.cx}" cy="-54" r="5"/>
                <circle class="lamp-glass" cx="${bay.cx}" cy="-54" r="3.2"/>
            </g>`,
        );

        const plateW = 28;
        out.push(
            S(
                k.slabOf(
                    {
                        x: bay.cx - plateW / 2,
                        y: BAY_FRONT,
                        w: plateW,
                        d: 1.2,
                        r: 1.2,
                    },
                    40,
                    8,
                    P,
                    3,
                    0.2,
                ),
                { tone: "hi", className: "quiz-label-plate" },
            ),
        );
        out.push(
            k.faceTextSvg(
                `${planeOf(BAY_FRONT - 1.3)} translate(${bay.cx} ${-44})`,
                String(bay.n + 1).padStart(2, "0"),
                { size: 4.2, tone: "mid", anchor: "middle" },
            ),
        );

        out.push(
            `<g class="quiz-socket" data-channel="${bay.n}" transform="${planeOf(BAY_FRONT)}">
                <path class="socket-body" d="${flat(bay.cx - 7, 24, bay.cx + 7, 34, 2)}"/>
                <path class="socket-ring" d="${flat(bay.cx - 5.5, 25.5, bay.cx + 5.5, 32.5, 1.5)}"/>
            </g>`,
        );

        for (const x of [bay.cx - 14, bay.cx + 14]) {
            out.push(
                D([at([x, BAY_FRONT, 21])], { size: 0.45, tone: "lo" }),
            );
        }
    }
    return out.join("");
}

function screen() {
    const scan = [];
    for (let z = SCREEN_IN.z0 + 4; z < SCREEN_IN.z1 - 2; z += 2.6) {
        scan.push(
            `M${SCREEN_IN.x0 + 4} ${(-z).toFixed(1)}H${SCREEN_IN.x1 - 4}`,
        );
    }
    const inside = `
        <rect x="${SCREEN_IN.x0 + 3}" y="${-(SCREEN_IN.z1 - 3)}" width="${SCREEN_IN.x1 - SCREEN_IN.x0 - 6}" height="${SCREEN_IN.z1 - SCREEN_IN.z0 - 6}" fill="url(#quiz-screen-glow)"/>
        <path class="quiz-scanlines" d="${scan.join("")}"/>
        <text class="quiz-screen-text" data-screen-line="1" x="${SCREEN_IN.x0 + 11}" y="${-(SCREEN_IN.z1 - 13)}">SALURAN 01</text>
        <text class="quiz-screen-text" data-screen-line="2" x="${SCREEN_IN.x0 + 11}" y="${-(SCREEN_IN.z1 - 27)}">PRATINJAU</text>
        <rect class="quiz-scanline" data-scan x="${SCREEN_IN.x0 + 6}" y="${-(SCREEN_IN.z0 + 9)}" width="${SCREEN_IN.x1 - SCREEN_IN.x0 - 12}" height="1.2"/>
    `;

    return recess(
        SCREEN,
        PANEL_FRONT,
        "",
        "quiz-bezel",
        recess(
            SCREEN_IN,
            PANEL_FRONT - SCREEN.depth,
            inside,
            "quiz-screen-back",
        ),
    );
}

function recess(
    { x0, x1, z0, z1, r, depth },
    y,
    back = "",
    backClass = "quiz-bezel",
    inside = "",
) {
    const id = `qm-clip-${recessId++}`;
    const ring = k.roundedPlan(
        { x: x0, y: z0, w: x1 - x0, d: z1 - z0, r },
        6,
    );
    const cx = (x0 + x1) / 2;
    const cz = (z0 + z1) / 2;
    const { sinA } = k.cameraOf(P);
    const tops = [];
    const sides = [[], [], [], []];
    ring.forEach(([ax, az], index) => {
        const [bx, bz] = ring[(index + 1) % ring.length];
        const length = Math.hypot(bx - ax, bz - az) || 1;
        let [nx, nz] = [(bz - az) / length, -(bx - ax) / length];
        if (
            nx * (cx - (ax + bx) / 2) + nz * (cz - (az + bz) / 2) <
            0
        ) {
            [nx, nz] = [-nx, -nz];
        }
        if (nx <= 0 && nz <= 0) return;
        const quad = k.pathOf(
            [
                at([ax, y, az]),
                at([bx, y, bz]),
                at([bx, y - depth, bz]),
                at([ax, y - depth, az]),
            ],
            true,
        );
        if (nz >= nx) {
            tops.push(quad);
        } else {
            const shade = Math.min(
                3,
                Math.max(0, Math.floor(((nx * -sinA + 1) / 2) * 4)),
            );
            sides[shade].push(quad);
        }
    });

    const shape = flat(x0, z0, x1, z1, r);
    return [
        `<clipPath id="${id}"><path transform="${planeOf(y)}" d="${shape}"/></clipPath>`,
        `<g clip-path="url(#${id})">`,
        `<path class="iso-top" d="${tops.join("")}"/>`,
        ...sides.map((paths, shade) =>
            paths.length
                ? `<path class="iso-shade" data-shade="${shade}" d="${paths.join("")}"/>`
                : "",
        ),
        `<g transform="${planeOf(y - depth)}"><path class="${backClass}" d="${shape}"/>${back}<path class="iso-line" data-tone="faint" d="${shape}"/></g>`,
        inside,
        `</g>`,
        `<g transform="${planeOf(y)}"><path class="iso-line" data-tone="lo" d="${shape}"/></g>`,
    ].join("");
}

let recessId = 0;

function rails() {
    const out = [];
    for (const y of RAIL_Y) {
        out.push(
            S(
                k.slabOf(
                    { x: -155, y: y - 3, w: 310, d: 6, r: 2 },
                    RAIL_Z,
                    RAIL_H,
                    P,
                    3,
                    0.4,
                ),
                { tone: "mid" },
            ),
        );
        for (const x of [-155, 155]) {
            out.push(
                S(
                    k.slabOf(
                        { x: x - 3, y: y - 4, w: 6, d: 8, r: 1.5 },
                        0,
                        7,
                        P,
                        3,
                        0.4,
                    ),
                    { tone: "mid" },
                ),
            );
        }
    }

    for (const bay of BAYS) {
        out.push(
            `<path class="quiz-notc" d="${topRect(bay.cx - 3, -37, bay.cx + 3, -31, 4.08)}"/>`,
        );
    }

    for (const y of RAIL_Y) {
        for (const z of [1.2, 2.8]) {
            out.push(
                L(
                    k.pathOf([
                        [-150, y - 3.02, z],
                        [150, y - 3.02, z],
                    ].map((p) => at(p))),
                    { tone: "faint" },
                ),
            );
        }
    }

    return out.join("");
}

function deck() {
    const out = [];
    for (const x of [-84, -60, -36, 36, 60, 84]) {
        out.push(
            L(
                k.planOutline(
                    { x, y: 9, w: 4, d: 20, r: 2 },
                    0.07,
                    P,
                ),
                { tone: "faint" },
            ),
        );
    }

    out.push(
        S(
            k.slabOf(
                { x: -130, y: 20, w: 30, d: 16, r: 2 },
                0,
                3,
                P,
                3,
                0.4,
            ),
            { tone: "mid" },
        ),
    );
    out.push(
        S(
            k.slabOf(
                { x: -122, y: 36, w: 14, d: 1.5, r: 0.8 },
                1.1,
                1.1,
                P,
                2,
                0.2,
            ),
            { tone: "lo" },
        ),
    );
    out.push(
        L(frontLine(-128, 2, -102, 2, 36.1), { tone: "faint" }),
    );

    return out.join("");
}

function frontControls() {
    const out = [];
    out.push(S(k.slabOf(FRONT, FRONT_Z, FRONT_H, P, 4, 0.8), { tone: "mid" }));

    out.push(
        S(
            k.slabOf(
                { x: -40, y: 64, w: 80, d: 2, r: 1.5 },
                1,
                3,
                P,
                3,
                0.2,
            ),
            { tone: "hi", className: "quiz-label-plate" },
        ),
    );
    out.push(
        k.faceTextSvg(
            `${planeOf(66.1)} translate(0 ${-2.2})`,
            "RUANG KENDALI",
            { size: 2.6, tone: "mid", anchor: "middle" },
        ),
    );
    out.push(
        L(frontLine(-34, 1.15, 34, 1.15, 66.12), { tone: "faint" }),
    );
    out.push(
        L(frontLine(-34, 3.55, 34, 3.55, 66.12), { tone: "faint" }),
    );

    for (const bay of BAYS) {
        out.push(
            `<g class="quiz-key" data-channel="${bay.n}">${S(
                k.cylinder(bay.cx, 52, 5.5, 4, 2, P, 24, 0.4),
                { tone: "hi" },
            )}${L(k.ring(bay.cx, 52, 4, 6.05, P, 24), { tone: "lo" })}${D(
                [at([bay.cx, 52, 6.04])],
                { size: 0.45, tone: "lo" },
            )}</g>`,
        );
    }

    const ticks = k.topTicks(-120, 120, 4, 5, 58, 4.06, [2, 4], P, "x");
    out.push(L(ticks.minor, { tone: "faint" }));
    out.push(L(ticks.major, { tone: "lo" }));

    return out.join("");
}

function needlePath(cx, cy, z, radius, tail, degrees) {
    const angle = (degrees * Math.PI) / 180;
    const dx = Math.sin(angle);
    const dy = -Math.cos(angle);
    const ax = axis([1, 0, 0]);
    const ay = axis([0, 1, 0]);
    const pivot = at([cx, cy, z]);
    const tip = [
        pivot[0] + (dx * radius * ax[0] + dy * radius * ay[0]),
        pivot[1] + (dx * radius * ax[1] + dy * radius * ay[1]),
    ];
    const back = [
        pivot[0] - (dx * tail * ax[0] + dy * tail * ay[0]),
        pivot[1] - (dx * tail * ax[1] + dy * tail * ay[1]),
    ];
    return k.pathOf([back, tip]);
}

function carriage() {
    const out = [];

    for (const x of [-28, 28]) {
        for (const y of RAIL_Y) {
            out.push(
                S(
                    k.slabOf(
                        { x: x - 5, y: y - 4, w: 10, d: 8, r: 2 },
                        4,
                        6,
                        P,
                        3,
                        0.4,
                    ),
                    { tone: "mid" },
                ),
            );
            out.push(
                S(k.cylinder(x, y, 2.7, 4, 3, P, 20), {
                    tone: "lo",
                    crease: "none",
                }),
            );
            out.push(L(k.ring(x, y, 2.7, 7.05, P, 20), { tone: "faint" }));
            out.push(D([at([x - 2.4, y - 2.4, 10.1])], { size: 0.35 }));
            out.push(D([at([x + 2.4, y + 2.4, 10.1])], { size: 0.35 }));
        }
    }

    out.push(S(k.slabOf(CAR, CAR_Z, CAR_H, P, 5, 0.8), { tone: "hi" }));
    out.push(
        L(k.planOutline(k.insetPlan(CAR, 4), CAR_Z + CAR_H + 0.05, P), {
            tone: "faint",
        }),
    );
    for (const [x, y] of [
        [CAR.x + 6, CAR.y + 6],
        [CAR.x + CAR.w - 6, CAR.y + 6],
        [CAR.x + 6, CAR.y + CAR.d - 6],
        [CAR.x + CAR.w - 6, CAR.y + CAR.d - 6],
    ]) {
        out.push(D([at([x, y, CAR_Z + CAR_H + 0.1])], { size: 0.4 }));
    }

    out.push(
        S(
            k.slabOf(
                { x: -30, y: -34, w: 60, d: 4, r: 2 },
                14,
                20,
                P,
                3,
                0.5,
            ),
            { tone: "mid" },
        ),
    );

    out.push(
        S(k.slabOf(CONSOLE, CONSOLE_Z, CONSOLE_H, P, 4, 0.6), {
            tone: "hi",
        }),
    );
    out.push(
        L(
            k.planOutline(
                k.insetPlan(
                    {
                        x: CONSOLE.x,
                        y: CONSOLE.y,
                        w: CONSOLE.w,
                        d: CONSOLE.d,
                        r: CONSOLE.r,
                    },
                    3,
                ),
                CONSOLE_Z + CONSOLE_H + 0.05,
                P,
            ),
            { tone: "faint" },
        ),
    );

    out.push(S(k.slabOf(DASH, DASH_Z, DASH_H, P, 3, 0.4), { tone: "hi" }));
    out.push(
        L(
            k.planOutline(
                k.insetPlan(
                    {
                        x: DASH.x,
                        y: DASH.y,
                        w: DASH.w,
                        d: DASH.d,
                        r: DASH.r,
                    },
                    2,
                ),
                DASH_Z + DASH_H + 0.04,
                P,
            ),
            { tone: "faint" },
        ),
    );

    out.push(
        `<g transform="${planeOf(5)}">
            <path class="quiz-slot" d="${flat(-26, 10, -14, 22, 2)}"/>
            <path class="iso-line" data-tone="lo" d="${flat(-26, 10, -14, 22, 2)}"/>
            <path class="quiz-tickline" d="M-12 -21V-11"/>
            ${[11, 13, 15, 17, 19, 21]
                .map((z) => `M-12 ${-z}H-10`)
                .join("")}
            <g data-gauge><path class="quiz-gauge-fill" d="${flat(-24, 10.5, -16, 13, 1)}"/></g>
        </g>`,
    );

    out.push(
        `<g class="quiz-participants" transform="${planeOf(5)}">${[
            12, 17,
        ]
            .map((z) =>
                [4, 9, 14, 19, 24, 29]
                    .map(
                        (x) =>
                            `<circle cx="${x}" cy="${-z}" r="1.45"/>`,
                    )
                    .join(""),
            )
            .join("")}</g>`,
    );

    out.push(
        S(
            k.slabOf(
                { x: 30, y: 4, w: 9, d: 3, r: 1 },
                21,
                2,
                P,
                3,
                0.3,
            ),
            { tone: "mid" },
        ),
    );
    out.push(
        S(k.cylinder(35, 5.5, 2.2, 23, 3.2, P, 16), {
            tone: "mid",
            crease: "none",
        }),
    );

    const feeder = [
        { x: -24, y: -24, w: 20, d: 2, r: 1 },
        { x: -24, y: -22, w: 2, d: 14, r: 1 },
        { x: -6, y: -22, w: 2, d: 14, r: 1 },
        { x: -24, y: -8, w: 20, d: 2, r: 1 },
    ];
    for (const plan of feeder) {
        out.push(
            S(k.slabOf(plan, 28, plan.y === -8 ? 12 : 16, P, 3, 0.4), {
                tone: "mid",
            }),
        );
    }
    out.push('<g class="quiz-cards">');
    for (let i = 0; i < 4; i++) {
        out.push(
            S(
                k.slabOf(
                    { x: -22, y: -21, w: 16, d: 12, r: 1 },
                    30 + i * 1.7,
                    0.6,
                    P,
                    3,
                    0.1,
                ),
                { tone: "hi" },
            ),
        );
    }
    out.push("</g>");
    out.push(
        `<g data-card>${S(
            k.slabOf(
                { x: -22, y: -14, w: 16, d: 10, r: 1 },
                38.5,
                0.7,
                P,
                3,
                0.1,
            ),
            { tone: "hi" },
        )}<path class="quiz-card-rule" d="${k.pathOf([
            at([-19, -11, 39.25]),
            at([-9, -11, 39.25]),
        ])}"/><path class="quiz-card-rule" d="${k.pathOf([
            at([-19, -8, 39.25]),
            at([-12, -8, 39.25]),
        ])}"/></g>`,
    );

    out.push(
        S(
            k.slabOf(
                { x: -24, y: -2, w: 20, d: 4, r: 1 },
                37,
                1.2,
                P,
                3,
                0.2,
            ),
            { tone: "hi" },
        ),
    );
    out.push(
        S(
            k.slabOf(
                { x: -24, y: -2, w: 20, d: 4, r: 1 },
                41,
                1.2,
                P,
                3,
                0.2,
            ),
            { tone: "hi" },
        ),
    );
    out.push(
        S(k.cylinder(-3.2, -12, 2.2, 34, 3, P, 16), {
            tone: "mid",
            crease: "none",
        }),
    );
    out.push(L(k.knurl(-3.2, -12, 2.2, 34, 37, 16, P), { tone: "lo" }));

    out.push(
        S(k.cylinder(DIAL.x, DIAL.y, DIAL.r, DIAL.z, DIAL.h, P, 48, 0.6), {
            tone: "hi",
        }),
    );
    out.push(
        L(
            k.knurl(
                DIAL.x,
                DIAL.y,
                DIAL.r,
                DIAL.z + 0.3,
                DIAL_TOP - 0.3,
                36,
                P,
            ),
            { tone: "lo" },
        ),
    );
    out.push(
        L(k.ring(DIAL.x, DIAL.y, DIAL.r - 1, DIAL_TOP + 0.05, P, 48), {
            tone: "lo",
        }),
    );
    const dialTicks = k.radialTicks(
        DIAL.x,
        DIAL.y,
        DIAL.r - 2.2,
        40,
        5,
        DIAL_TOP + 0.08,
        [1, 2],
        P,
        Math.PI * 0.25,
        Math.PI * 1.5,
    );
    out.push(L(dialTicks.minor, { tone: "faint" }));
    out.push(L(dialTicks.major, { tone: "lo" }));
    out.push(
        `<path class="quiz-needle" data-needle d="${needlePath(
            DIAL.x,
            DIAL.y,
            DIAL_TOP + 0.3,
            DIAL.r - 3,
            2.5,
            -135,
        )}"/>`,
    );
    out.push(
        S(k.cylinder(DIAL.x, DIAL.y, 1.6, DIAL_TOP + 0.2, 1.2, P, 16), {
            tone: "mid",
            crease: "none",
        }),
    );

    out.push(
        S(
            k.slabOf(
                { x: 22, y: -22, w: 12, d: 14, r: 2 },
                28,
                6,
                P,
                3,
                0.4,
            ),
            { tone: "mid" },
        ),
    );
    out.push(
        S(k.cylinder(28, -16, 6, 34, 10, P, 32, 0.5), { tone: "hi" }),
    );
    out.push(
        S(k.cylinder(28, -16, 2.2, 44, 1.2, P, 20), {
            tone: "mid",
            crease: "none",
        }),
    );
    out.push(
        `<path class="quiz-paper" d="${sideRect(34, -10, 30, -2, 42)}"/>`,
    );
    out.push(
        L(sideRect(34, -10, 30, -2, 42), { tone: "faint" }),
    );
    for (const z of [32, 34, 36, 38, 40]) {
        out.push(
            L(
                k.pathOf([
                    at([34, -9, z]),
                    at([34, -3, z]),
                ]),
                { tone: "faint" },
            ),
        );
    }
    out.push(
        `<g data-print>${S(
            k.slabOf(
                { x: 33.6, y: -8, w: 2.8, d: 4, r: 0.8 },
                37,
                2,
                P,
                2,
                0.2,
            ),
            { tone: "hi" },
        )}</g>`,
    );

    out.push(
        `<g data-plug>${S(
            k.slabOf(
                { x: -5, y: -40, w: 10, d: 10, r: 1.5 },
                25,
                7,
                P,
                3,
                0.5,
            ),
            { tone: "hi", lit: true },
        )}${S(
            k.slabOf(
                { x: -3, y: -41.2, w: 6, d: 1.2, r: 0.6 },
                27,
                3,
                P,
                2,
                0.2,
            ),
            { tone: "hi", lit: true },
        )}</g>`,
    );
    out.push(
        S(
            k.slabOf(
                { x: -6, y: -31, w: 12, d: 2, r: 1 },
                24,
                9,
                P,
                3,
                0.4,
            ),
            { tone: "mid" },
        ),
    );

    out.push(
        `<g data-plunger>${S(
            k.cylinder(0, -34, 2.4, 4, 6, P, 20),
            { tone: "mid", crease: "none" },
        )}${S(k.cylinder(0, -34, 3.2, 9.4, 3.2, P, 20), {
            tone: "lo",
            crease: "none",
        })}${L(k.ring(0, -34, 3.2, 12.7, P, 20), { tone: "faint" })}</g>`,
    );

    out.push(
        S(
            k.slabOf(
                { x: -39, y: -24, w: 3, d: 8, r: 1 },
                28,
                6,
                P,
                3,
                0.4,
            ),
            { tone: "mid" },
        ),
    );

    return `<g data-carriage transform="${k.translateAlong(P, [
        BAYS[0].cx,
        0,
        0,
    ])}">${out.join("")}</g>`;
}

function reelAndBell() {
    const out = [];

    out.push(
        S(
            k.cylinder(
                REEL.x,
                REEL.y,
                9,
                BEAM_Z + BEAM_H,
                12,
                P,
                40,
                0.6,
            ),
            { tone: "mid" },
        ),
    );
    out.push(
        L(
            k.knurl(
                REEL.x,
                REEL.y,
                9,
                BEAM_Z + BEAM_H + 1,
                BEAM_Z + BEAM_H + 11,
                36,
                P,
                1,
            ),
            { tone: "lo" },
        ),
    );
    out.push(
        S(
            k.cylinder(
                REEL.x,
                REEL.y,
                6,
                BEAM_Z + BEAM_H + 12,
                1.2,
                P,
                32,
            ),
            { tone: "hi" },
        ),
    );
    out.push(
        S(
            k.cylinder(
                REEL.x,
                REEL.y,
                2.6,
                BEAM_Z + BEAM_H + 13,
                1,
                P,
                20,
            ),
            { tone: "mid", crease: "none" },
        ),
    );
    out.push(
        S(
            k.slabOf(
                { x: REEL.x - 4, y: -53, w: 8, d: 11, r: 1 },
                166,
                2,
                P,
                3,
                0.3,
            ),
            { tone: "mid" },
        ),
    );
    out.push(
        S(
            k.slabOf(
                { x: REEL.x - 3, y: -43, w: 6, d: 2, r: 1 },
                165,
                4,
                P,
                2,
                0.2,
            ),
            { tone: "hi" },
        ),
    );

    out.push(
        S(k.cylinder(BELL.x, BELL.y, 10, BEAM_Z + BEAM_H, 2, P, 40), {
            tone: "mid",
        }),
    );
    out.push(
        S(k.cylinder(BELL.x, BELL.y, 8, BEAM_Z + BEAM_H + 2, 4, P, 40), {
            tone: "mid",
        }),
    );
    out.push(
        S(k.cylinder(BELL.x, BELL.y, 6, BEAM_Z + BEAM_H + 6, 3, P, 32), {
            tone: "mid",
        }),
    );
    out.push(
        S(k.cylinder(BELL.x, BELL.y, 3.6, BEAM_Z + BEAM_H + 9, 2, P, 24), {
            tone: "mid",
        }),
    );
    out.push(
        S(k.cylinder(BELL.x, BELL.y, 1.6, BEAM_Z + BEAM_H + 11, 1, P, 16), {
            tone: "hi",
        }),
    );
    out.push(L(k.ring(BELL.x, BELL.y, 10, BEAM_Z + BEAM_H + 2.05, P, 40), {
        tone: "lo",
    }));

    out.push(
        S(k.cylinder(98, -45, 2.2, BEAM_Z + BEAM_H, 4, P, 16), {
            tone: "mid",
            crease: "none",
        }),
    );
    out.push(
        `<g data-bell-striker>${S(
            k.slabOf(
                { x: 98, y: -46.5, w: 7, d: 2.4, r: 1 },
                165,
                1.6,
                P,
                2,
                0.2,
            ),
            { tone: "mid" },
        )}${S(
            k.cylinder(105, -45.3, 2.4, 164.2, 3.2, P, 20),
            { tone: "hi" },
        )}</g>`,
    );

    return out.join("");
}

function fixedCable() {
    const points = [
        REEL_GUIDE,
        [-138, -42, 158],
        [-146, -42, 150],
        GUIDE,
    ].map(at);
    const d = k.pathOf(points);
    const clamps = [];
    for (const [x, y, z] of [
        [-138, -42, 158],
        [-146, -42, 150],
        [-146, -42, 130],
        [-146, -42, 110],
        [-146, -42, 96],
    ]) {
        const p = at([x, y, z]);
        clamps.push(
            `<g transform="translate(${p[0].toFixed(1)} ${p[1].toFixed(1)})"><rect x="-2.1" y="-1.5" width="4.2" height="3" rx=".7" fill="var(--anatomy-face)" stroke="var(--anatomy-lo)" stroke-width=".5"/></g>`,
        );
    }
    return `${L(d, { tone: "mid" })}${clamps.join("")}${D(
        [at(REEL_GUIDE), at(GUIDE)],
        { size: 0.55, tone: "lo" },
    )}`;
}

function cableData() {
    const originX = BAYS[0].cx;
    return {
        cornerBase: at([originX - 38, -42, 82]),
        endBase: at([originX - 38, -20, 30]),
    };
}

function cable() {
    const originX = BAYS[0].cx;
    const guide = at(GUIDE);
    const { cornerBase, endBase } = cableData();
    const corner = cornerBase;
    const end = endBase;
    const mid = [(guide[0] + corner[0]) / 2, (guide[1] + corner[1]) / 2 + 5];
    const d = `M${guide[0].toFixed(1)} ${guide[1].toFixed(1)} Q${mid[0].toFixed(1)} ${mid[1].toFixed(1)} ${corner[0].toFixed(1)} ${corner[1].toFixed(1)} L${end[0].toFixed(1)} ${end[1].toFixed(1)}`;
    return `<g data-live-cable><path class="quiz-cable-shadow" d="${d}"/><path class="quiz-cable" data-cable d="${d}"/><circle class="quiz-cable-end" data-cable-end cx="${end[0].toFixed(1)}" cy="${end[1].toFixed(1)}" r="2.1"/></g>`;
}

/* --------------------------------------------------------------------- build */

const carriageOrigin = BAYS[0].cx;
const carriageBase = axis([carriageOrigin, 0, 0]);
const screenInside = screen();

const body = [
    base(),
    ledgerBar(),
    backFrame(),
    screenInside,
    bays(),
    rails(),
    deck(),
    reelAndBell(),
    fixedCable(),
    carriage(),
    cable(),
    frontControls(),
];

const svg = k.figureSvg({
    width: WIDTH,
    height: HEIGHT,
    label:
        "Mesin hubung kuis multi-organisasi: carriage di atas rel memilih satu dari enam saluran organisasi, lalu menghubungkan kartu soal, timer, lampu peserta, dan laporan.",
    className: "quiz-machine-svg",
    body: `<defs><linearGradient id="quiz-screen-glow" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b2a42"/><stop offset=".55" stop-color="#0c4a6e"/><stop offset="1" stop-color="#082f49"/></linearGradient></defs>${body.join("")}`,
});

const needlePivot = at([DIAL.x, DIAL.y, DIAL_TOP + 0.3]);
const data = {
    originX: carriageOrigin,
    carriage: { base: carriageBase, axis: axis([1, 0, 0]) },
    yAxis: axis([0, 1, 0]),
    zAxis: axis([0, 0, 1]),
    channels: BAYS.map((bay) => ({ x: bay.cx, label: String(bay.n + 1).padStart(2, "0") })),
    plug: { retract: 6 },
    card: { travel: 10 },
    gauge: { travel: 10 },
    print: { travel: 4.5 },
    needle: {
        pivot: needlePivot,
        axisX: axis([1, 0, 0]),
        axisY: axis([0, 1, 0]),
        radius: DIAL.r - 3,
        tail: 2.5,
    },
    bell: { pivot: at(BELL_PIVOT), angle: 9 },
    cable: {
        guide: at(GUIDE),
        ...cableData(),
        sag: 5,
        axis: axis([1, 0, 0]),
    },
    screen: { max: 24 },
    parts: (body.join("").match(/class="iso-solid/g) || []).length,
};

const root = `<div class="quiz-machine" data-quiz-machine-root data-state="idle" aria-hidden="true">${svg}<script type="application/json" data-quiz-machine-data>${JSON.stringify(data)}</script></div>`;

mkdirSync(join(ROOT, "views", "partials", "landing"), { recursive: true });
writeFileSync(
    join(ROOT, "views", "partials", "landing", "quiz-machine.blade.php"),
    root,
);

const quizCss = readFileSync(join(ROOT, "css", "quiz-machine.css"), "utf8");
const liveJs = readFileSync(
    join(ROOT, "js", "landing-machine.js"),
    "utf8",
);
const standalone = k
    .pageHtml({
        title: "Kuis control machine",
        theme: "light",
        width: 1100,
        body: k.plateHtml({
            fig: "Fig 1",
            title: "Kuis control machine",
            hint: "Enam saluran, satu carriage",
            readout: "Pratinjau alur",
            keys: [
                { mark: "lit", label: "Saluran aktif" },
                { mark: "raised", label: "Kunci mekanis" },
                { mark: "dotted", label: "Alur pratinjau" },
            ],
            caption:
                "Carriage berjalan di rel dan mengunci satu saluran organisasi. Saluran lain tetap terputus.",
            body: `<div class="iso-stage">${root}</div><div class="quiz-standalone-readout" data-quiz-readout><span data-readout-text>Pratinjau alur.</span></div>`,
        }),
        script: liveJs,
    })
    .replace("</style>", `${quizCss}</style>`);
writeFileSync(join(HERE, "quiz-machine.html"), standalone);

console.log(
    `built ${data.parts} solids, ${WIDTH}x${HEIGHT}, ${(svg.length / 1024).toFixed(1)} kB`,
);
