// Generates views/partials/registration/workspace-press.blade.php from the local isometric kit.
// Run: node resources/js/anatomy/workspace-press/build.mjs
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import * as k from "../kit/iso-kit.mjs";

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, "..", "..", "..");
const WIDTH = 700;
const HEIGHT = 570;
const PAD = 24;

/* ---------------------------------------------------------------- geometry */

const BASE = { x: -154, y: -74, w: 308, d: 148, r: 14 };
const BASE_H = 12;
const FOOT_H = 7;
const BASE_TOP = 0;

const LIP = { x: -154, y: 60, w: 308, d: 16, r: 5 };
const LIP_H = 5;

const POST_L = { x: -102, y: -62, w: 18, d: 18, r: 3 };
const POST_R = { x: 84, y: -62, w: 18, d: 18, r: 3 };
const POST_H = 150;
const POST_CENTRES = [
  [-93, -53],
  [93, -53],
];

const BED = { x: -114, y: -56, w: 228, d: 104, r: 10 };
const BED_Z = 16;
const BED_H = 14;
const BED_TOP = BED_Z + BED_H;

const WORK = { x: -54, y: -32, w: 108, d: 60, r: 3 };
const WORK_Z = BED_TOP;
const WORK_H = 4;
const WORK_TOP = WORK_Z + WORK_H;

const RAM = { x: -74, y: -38, w: 148, d: 68, r: 6 };
const RAM_Z = 78;
const RAM_H = 15;
const RAM_TOP = RAM_Z + RAM_H;

const GUIDE_L = { x: -104, y: -64, w: 38, d: 30, r: 4 };
const GUIDE_R = { x: 66, y: -64, w: 38, d: 30, r: 4 };
const GUIDE_Z = 78;
const GUIDE_H = 15;

const HEAD = { x: -32, y: -26, w: 64, d: 44, r: 4 };
const HEAD_Z = 64;
const HEAD_H = 15;
const HEAD_TOP = HEAD_Z + HEAD_H;
const PAD_R = 14;
const PAD_Z = HEAD_Z - 4;
const PAD_H = 5;

const SCREW = [0, -4];
const SCREW_R = 4.6;
const SCREW_FROM = RAM_TOP - 1;
const SCREW_TO = 170;

const BEAM = { x: -118, y: -68, w: 236, d: 84, r: 7 };
const BEAM_Z = 150;
const BEAM_H = 15;
const BEAM_TOP = BEAM_Z + BEAM_H;

const WHEEL = [0, -4];
const WHEEL_R = 42;
const WHEEL_Z = 168;
const WHEEL_H = 6;
const WHEEL_TOP = WHEEL_Z + WHEEL_H;
const HUB_R = 10;
const HUB_H = 7;

const CONSOLE = { x: -128, y: 42, w: 94, d: 24, r: 5 };
const CONSOLE_Z = 0;
const CONSOLE_H = 18;
const CONSOLE_FRONT = CONSOLE.y + CONSOLE.d;

const TRAY = { x: 48, y: 34, w: 72, d: 30, r: 4 };
const TRAY_Z = 0;
const TRAY_H = 3;

/* --------------------------------------------------------------- projection */

const FIT = k.boxCorners({ x: -172, y: -90, w: 344, d: 180 }, -19, 198);
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

const frontLine = (x0, z0, x1, z1, y) =>
  k.pathOf([at([x0, y, z0]), at([x1, y, z1])]);

const frontRect = (x0, z0, x1, z1, y) =>
  k.pathOf(
    [
      at([x0, y, z0]),
      at([x1, y, z0]),
      at([x1, y, z1]),
      at([x0, y, z1]),
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
    `<path class="iso-halo" d="${k.haloOf(BASE, -BASE_H - FOOT_H, BASE_H, P)}"/>`,
  );

  for (const [x, y] of k.corners(BASE, 26)) {
    out.push(
      S(k.cylinder(x, y, 8, -BASE_H - FOOT_H, 2, P, 24), {
        tone: "lo",
        crease: "none",
      }),
      S(
        k.cylinder(
          x,
          y,
          6.2,
          -BASE_H - FOOT_H + 2,
          FOOT_H - 2,
          P,
          24,
        ),
        { tone: "lo", crease: "none" },
      ),
    );
  }

  out.push(S(k.slabOf(BASE, -BASE_H, BASE_H, P, 10, 1.7), { tone: "mid" }));
  out.push(
    L(k.planOutline(k.insetPlan(BASE, 7), 0.05, P), { tone: "lo" }),
  );
  out.push(
    L(k.planOutline(k.insetPlan(BASE, 15), 0.07, P), { tone: "faint" }),
  );

  for (const [x, y] of k.corners(BASE, 22)) {
    out.push(
      S(k.cylinder(x, y, 3.4, 0, 1.6, P, 20), { tone: "hi" }),
      L(k.ring(x, y, 3.4, 1.62, P, 20), { tone: "lo" }),
    );
  }

  out.push(
    S(k.slabOf({ ...LIP, x: LIP.x }, BASE_TOP, LIP_H, P, 5, 0.8), {
      tone: "hi",
    }),
  );

  const ticks = k.topTicks(
    -142,
    142,
    10,
    5,
    LIP.y + 2,
    BASE_TOP + LIP_H + 0.08,
    [3.5, 7],
    P,
    "x",
  );
  out.push(L(ticks.minor, { tone: "faint" }));
  out.push(L(ticks.major, { tone: "lo" }));

  out.push(
    L(
      topRect(-142, LIP.y + 12, 142, LIP.y + 12.8, BASE_TOP + LIP_H + 0.08),
      { tone: "faint" },
    ),
  );

  const labelY = LIP.y + LIP.d + 0.06;
  out.push(
    L(frontRect(-44, 0.75, 44, 4.25, labelY), { tone: "lo" }),
    k.faceTextSvg(
      `${planeOf(labelY + 0.12)} translate(0 ${-2.6})`,
      "MEJA TERBIT / 01",
      { size: 2.35, tone: "mid", anchor: "middle" },
    ),
  );

  for (const x of [-132, 132]) {
    out.push(
      L(frontLine(x, -9.4, x, -2.4, BASE.y + BASE.d + 0.06), {
        tone: "faint",
      }),
    );
  }

  return out.join("");
}

function posts() {
  const out = [];

  for (const post of [POST_L, POST_R]) {
    const cx = post.x + post.w / 2;
    const cy = post.y + post.d / 2;

    out.push(
      S(
        k.slabOf(
          { x: cx - 13, y: cy - 13, w: 26, d: 26, r: 4 },
          0,
          6,
          P,
          4,
          0.5,
        ),
        { tone: "mid" },
      ),
    );

    out.push(
      S(k.slabOf(post, 0, POST_H, P, 4, 1.1), {
        tone: "mid",
        className: "press-post",
      }),
    );

    out.push(
      L(k.planOutline(k.insetPlan(post, 3), POST_H + 0.06, P), {
        tone: "lo",
      }),
    );

    for (const [dx, dy] of [
      [-9, -9],
      [9, -9],
      [-9, 9],
      [9, 9],
    ]) {
      out.push(
        S(k.cylinder(cx + dx, cy + dy, 2.1, 6, 1.2, P, 16), {
          tone: "hi",
          crease: "none",
        }),
      );
    }
  }

  return out.join("");
}

function bed() {
  const out = [];

  out.push(S(k.slabOf(BED, BED_Z, BED_H, P, 9, 1.2), { tone: "mid" }));
  out.push(
    L(k.planOutline(k.insetPlan(BED, 7), BED_TOP + 0.05, P), {
      tone: "lo",
    }),
  );

  out.push(
    S(k.slabOf(WORK, WORK_Z, WORK_H, P, 4, 0.6), {
      tone: "hi",
      className: "press-blank",
    }),
  );
  out.push(
    L(k.planOutline(k.insetPlan(WORK, 3.5), WORK_TOP + 0.06, P), {
      tone: "faint",
    }),
  );

  for (const [x, y] of k.corners(WORK, 6)) {
    out.push(
      S(k.cylinder(x, y, 2.1, WORK_TOP, 2, P, 16), {
        tone: "mid",
        crease: "none",
      }),
    );
  }

  out.push(
    k.faceTextSvg(
      `${k.topMatrix([0, -4], WORK_TOP + 0.1, P, "x")} translate(0 0)`,
      "PLAT 01",
      { size: 4.1, tone: "faint", anchor: "middle" },
    ),
  );

  for (const [x, y] of [
    [-96, -42],
    [96, -42],
    [-96, 34],
    [96, 34],
  ]) {
    out.push(
      S(k.cylinder(x, y, 2.5, BED_TOP, 1.2, P, 16), {
        tone: "hi",
        crease: "none",
      }),
    );
  }

  out.push(
    L(frontLine(-108, BED_Z + 4, 108, BED_Z + 4, BED.y + BED.d), {
      tone: "faint",
    }),
  );

  return out.join("");
}

function controlConsole() {
  const out = [];

  out.push(
    S(k.slabOf(CONSOLE, CONSOLE_Z, CONSOLE_H, P, 5, 0.9), { tone: "mid" }),
  );
  out.push(
    L(
      k.planOutline(k.insetPlan(CONSOLE, 5), CONSOLE_H + 0.06, P),
      { tone: "lo" },
    ),
  );

  out.push(
    `<path class="press-panel" d="${frontRect(
      CONSOLE.x + 7,
      2.8,
      CONSOLE.x + CONSOLE.w - 7,
      CONSOLE_H - 2,
      CONSOLE_FRONT + 0.08,
    )}"/>`,
  );
  out.push(
    L(
      frontLine(
        CONSOLE.x + 9,
        3.9,
        CONSOLE.x + CONSOLE.w - 9,
        3.9,
        CONSOLE_FRONT + 0.14,
      ),
      { tone: "faint" },
    ),
  );

  const lampX = [-115, -98, -81, -64, -47];
  lampX.forEach((x, index) => {
    out.push(
      `<g class="press-lamp" data-step="${index}" transform="${planeOf(CONSOLE_FRONT + 0.1)}">
        <circle class="lamp-glow" cx="${x}" cy="-9" r="8.4"/>
        <circle class="lamp-ring" cx="${x}" cy="-9" r="5.8"/>
        <circle class="lamp-glass" cx="${x}" cy="-9" r="3.6"/>
      </g>`,
    );
  });

  out.push(
    L(
      frontLine(-122, 2.4, -34, 2.4, CONSOLE_FRONT + 0.1),
      { tone: "faint" },
    ),
  );

  for (const x of [-122, -34]) {
    out.push(
      S(k.cylinder(x, CONSOLE.y + CONSOLE.d - 5, 2.2, CONSOLE_H, 1.3, P, 16), {
        tone: "hi",
        crease: "none",
      }),
    );
  }

  return out.join("");
}

function tray() {
  const out = [];

  out.push(
    S(k.slabOf(TRAY, TRAY_Z, TRAY_H, P, 4, 0.4), { tone: "lo" }),
  );
  out.push(
    L(k.planOutline(k.insetPlan(TRAY, 4), TRAY_Z + TRAY_H + 0.05, P), {
      tone: "faint",
    }),
  );

  for (let index = 0; index < 3; index++) {
    out.push(
      S(
        k.slabOf(
          {
            x: TRAY.x + 9 + index * 0.7,
            y: TRAY.y + 8 + index * 0.5,
            w: 54,
            d: 16,
            r: 2,
          },
          TRAY_Z + TRAY_H + index * 1.5,
          1.3,
          P,
          3,
          0.3,
        ),
        { tone: index === 2 ? "hi" : "mid", className: "press-blank" },
      ),
    );
  }

  out.push(
    L(
      topRect(
        TRAY.x + 10,
        TRAY.y + 8,
        TRAY.x + 64,
        TRAY.y + 24,
        TRAY_Z + TRAY_H + 4.65,
      ),
      { tone: "faint" },
    ),
  );

  return out.join("");
}

function ram() {
  const out = [];

  for (const guide of [GUIDE_L, GUIDE_R]) {
    out.push(
      S(k.slabOf(guide, GUIDE_Z, GUIDE_H, P, 4, 0.8), { tone: "mid" }),
    );
  }

  out.push(S(k.slabOf(RAM, RAM_Z, RAM_H, P, 6, 1.2), { tone: "hi" }));
  out.push(
    L(k.planOutline(k.insetPlan(RAM, 5), RAM_TOP + 0.06, P), {
      tone: "lo",
    }),
  );

  for (const [x, y] of POST_CENTRES) {
    out.push(
      S(k.cylinder(x, y, 12.2, GUIDE_Z - 1.5, GUIDE_H + 4, P, 32, 0.7), {
        tone: "mid",
      }),
    );
    out.push(
      L(k.ring(x, y, 12.2, GUIDE_Z + GUIDE_H + 2.55, P, 32), {
        tone: "lo",
      }),
    );
    out.push(
      L(k.ring(x, y, 8.3, GUIDE_Z + GUIDE_H + 2.58, P, 28), {
        tone: "faint",
      }),
    );
  }

  out.push(
    S(k.slabOf(HEAD, HEAD_Z, HEAD_H, P, 4, 0.8), {
      tone: "mid",
      className: "press-head",
    }),
  );
  out.push(
    S(k.cylinder(SCREW[0], SCREW[1], PAD_R, PAD_Z, PAD_H, P, 40, 0.6), {
      tone: "hi",
    }),
  );
  out.push(
    L(k.ring(SCREW[0], SCREW[1], PAD_R - 2.2, PAD_Z + PAD_H + 0.06, P, 40), {
      tone: "lo",
    }),
  );

  out.push(
    S(
      k.cylinder(
        SCREW[0],
        SCREW[1],
        SCREW_R,
        SCREW_FROM,
        SCREW_TO - SCREW_FROM,
        P,
        24,
        0.4,
      ),
      { tone: "hi", className: "press-screw" },
    ),
  );

  for (let z = SCREW_FROM + 5; z < SCREW_TO - 4; z += 5.2) {
    out.push(
      L(k.sideArc(SCREW[0], SCREW[1], SCREW_R, z, P, 20), {
        tone: "faint",
      }),
    );
  }

  out.push(
    S(
      k.cylinder(
        SCREW[0],
        SCREW[1],
        SCREW_R + 3.8,
        SCREW_FROM,
        6,
        P,
        28,
        0.5,
      ),
      { tone: "mid" },
    ),
  );

  return out.join("");
}

function beam() {
  const out = [];

  out.push(S(k.slabOf(BEAM, BEAM_Z, BEAM_H, P, 8, 1.3), { tone: "mid" }));
  out.push(
    L(k.planOutline(k.insetPlan(BEAM, 7), BEAM_TOP + 0.06, P), {
      tone: "lo",
    }),
  );

  for (const [x, y] of POST_CENTRES) {
    out.push(
      S(k.cylinder(x, y, 7.5, BEAM_TOP, 5, P, 24, 0.5), {
        tone: "hi",
      }),
    );
    out.push(
      L(k.ring(x, y, 7.5, BEAM_TOP + 5.06, P, 24), { tone: "lo" }),
    );
  }

  out.push(
    S(
      k.cylinder(SCREW[0], SCREW[1], 14, BEAM_TOP, 3, P, 32, 0.5),
      { tone: "hi" },
    ),
  );
  out.push(
    L(k.ring(SCREW[0], SCREW[1], 14, BEAM_TOP + 3.06, P, 32), {
      tone: "lo",
    }),
  );

  const faceY = BEAM.y + BEAM.d + 0.08;
  out.push(
    `<path class="press-label-plate" d="${frontRect(
      50,
      BEAM_Z + 1.6,
      114,
      BEAM_Z + 13.4,
      faceY,
    )}"/>`,
  );
  out.push(
    `<text class="press-plate-text" transform="${planeOf(
      faceY + 0.12,
    )} translate(82 ${-(BEAM_Z + 8.1)})" font-size="4.2" text-anchor="middle">MEJA TERBIT</text>`,
  );
  out.push(
    L(
      frontLine(50, BEAM_Z + 2.3, 114, BEAM_Z + 2.3, faceY + 0.14),
      { tone: "faint" },
    ),
  );
  out.push(
    L(
      frontLine(50, BEAM_Z + 12.2, 114, BEAM_Z + 12.2, faceY + 0.14),
      { tone: "faint" },
    ),
  );

  for (const x of [-102, -84, 84, 102]) {
    out.push(
      D([at([x, BEAM.y + 9, BEAM_TOP + 0.12])], {
        size: 0.45,
        tone: "faint",
      }),
    );
  }

  return out.join("");
}

function wheel() {
  const out = [];

  out.push(
    S(
      k.cylinder(
        WHEEL[0],
        WHEEL[1],
        WHEEL_R,
        WHEEL_Z,
        WHEEL_H,
        P,
        64,
        0.7,
      ),
      { tone: "mid", className: "press-wheel" },
    ),
  );

  out.push(
    L(k.ring(WHEEL[0], WHEEL[1], WHEEL_R - 4, WHEEL_TOP + 0.06, P, 64), {
      tone: "lo",
    }),
  );
  out.push(
    L(k.ring(WHEEL[0], WHEEL[1], 30, WHEEL_TOP + 0.07, P, 48), {
      tone: "faint",
    }),
  );

  for (let index = 0; index < 6; index++) {
    const angle = (index / 6) * Math.PI * 2 + Math.PI / 12;
    const ux = Math.cos(angle);
    const uy = Math.sin(angle);
    const half = 2.8;
    const inner = 11;
    const outer = 34;
    const ring = [
      [WHEEL[0] + ux * inner - uy * half, WHEEL[1] + uy * inner + ux * half],
      [WHEEL[0] + ux * outer - uy * half, WHEEL[1] + uy * outer + ux * half],
      [WHEEL[0] + ux * outer + uy * half, WHEEL[1] + uy * outer - ux * half],
      [WHEEL[0] + ux * inner + uy * half, WHEEL[1] + uy * inner - ux * half],
    ];
    out.push(
      S(k.extrude(ring, WHEEL_TOP + 0.08, 2.8, P, { convex: true }), {
        tone: "hi",
      }),
    );
  }

  out.push(
    S(
      k.cylinder(
        WHEEL[0],
        WHEEL[1],
        HUB_R,
        WHEEL_TOP,
        HUB_H,
        P,
        36,
        0.5,
      ),
      { tone: "hi" },
    ),
  );
  out.push(
    L(k.ring(WHEEL[0], WHEEL[1], HUB_R, WHEEL_TOP + HUB_H + 0.06, P, 36), {
      tone: "lo",
    }),
  );
  out.push(
    D([at([WHEEL[0], WHEEL[1], WHEEL_TOP + HUB_H + 0.08])], {
      size: 0.7,
      tone: "lo",
    }),
  );

  out.push(
    S(
      k.slabOf(
        { x: WHEEL[0] + 18, y: WHEEL[1] - 2.7, w: 36, d: 5.4, r: 2.4 },
        WHEEL_TOP + 0.2,
        3.4,
        P,
        4,
        0.3,
      ),
      { tone: "hi" },
    ),
  );
  out.push(
    S(
      k.cylinder(WHEEL[0] + 54, WHEEL[1], 3.4, WHEEL_TOP + 3.6, 10, P, 24, 0.4),
      { tone: "mid" },
    ),
  );
  out.push(
    S(
      k.cylinder(
        WHEEL[0] + 54,
        WHEEL[1],
        5.3,
        WHEEL_TOP + 13.6,
        3.2,
        P,
        24,
        0.4,
      ),
      { tone: "hi" },
    ),
  );

  for (let index = 0; index < 8; index++) {
    const angle = (index / 8) * Math.PI * 2;
    const x = WHEEL[0] + Math.cos(angle) * 36.5;
    const y = WHEEL[1] + Math.sin(angle) * 36.5;
    out.push(D([at([x, y, WHEEL_TOP + 0.09])], { size: 0.55, tone: "lo" }));
  }

  return out.join("");
}

/* --------------------------------------------------------------------- build */

const body = [
  base(),
  posts(),
  bed(),
  controlConsole(),
  tray(),
  ram(),
  beam(),
  wheel(),
];

const svg = k.figureSvg({
  width: WIDTH,
  height: HEIGHT,
  label:
    "Mesin press plat workspace: landasan berlabel, dua tiang, ram pemandu, roda tangan, dan lima lampu kesiapan.",
  className: "workspace-press-svg",
  body: body.join(""),
});

const root = `<div class="workspace-press" data-workspace-press data-ready="false" aria-hidden="true">${svg}</div>`;

mkdirSync(join(ROOT, "views", "partials", "registration"), { recursive: true });
writeFileSync(
  join(ROOT, "views", "partials", "registration", "workspace-press.blade.php"),
  root,
);

const page = k
  .pageHtml({
    title: "Meja Terbit press",
    theme: "light",
    width: 820,
    body: `<div class="workspace-press">${svg}</div>`,
  })
  .replace(
    "</style>",
    `${readFileSync(join(ROOT, "css", "registration.css"), "utf8")}</style>`,
  );
writeFileSync(join(HERE, "workspace-press.html"), page);

const parts = (body.join("").match(/class="iso-solid/g) || []).length;
console.log(
  `built ${parts} solids, ${WIDTH}x${HEIGHT}, ${(svg.length / 1024).toFixed(1)} kB`,
);
