const fs = require("fs");
const path = require("path");

function loadPptxGen() {
  try {
    return require("pptxgenjs");
  } catch {
    return require("C:/Users/stefa/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/.pnpm/pptxgenjs@4.0.1/node_modules/pptxgenjs");
  }
}

const pptxgen = loadPptxGen();
const pptx = new pptxgen();

pptx.layout = "LAYOUT_WIDE";
pptx.author = "Codex";
pptx.company = "SIM-KK";
pptx.subject = "Architecture deck generated from ARCHITECTURE.md";
pptx.title = "SIM-KK Architecture Deck";
pptx.lang = "id-ID";
pptx.theme = {
  headFontFace: "Trebuchet MS",
  bodyFontFace: "Aptos",
  lang: "id-ID",
};
pptx.margin = 0;
pptx.defineSlideMaster({
  title: "SIMKK",
  background: { color: "FAFAF7" },
  objects: [],
});

const S = pptx.ShapeType || {};
const shapes = new pptxgen().shapes;
const SH = {
  rect: shapes.RECTANGLE,
  round: shapes.ROUNDED_RECTANGLE,
  line: shapes.LINE,
  oval: shapes.OVAL,
  chevron: shapes.CHEVRON,
  arrow: shapes.RIGHT_ARROW,
  arc: shapes.ARC,
};

const W = 13.333;
const H = 7.5;
const C = {
  base: "FAFAF7",
  surface: "EEF1EE",
  ink: "111827",
  muted: "5C6470",
  green: "1F5F50",
  green2: "2D7A68",
  amber: "B98948",
  brass: "D2B06D",
  blue: "2E5C7A",
  clay: "9C4E3D",
  rose: "E8D8CF",
  white: "FFFFFF",
  line: "D6DDD8",
};

const OUT_DIR = path.resolve("outputs/sim-kk-architecture");
const OUT = path.join(OUT_DIR, "SIM-KK-Architecture-Deck.pptx");
fs.mkdirSync(OUT_DIR, { recursive: true });

function addShape(slide, type, opts) {
  slide.addShape(type, opts);
}

function addRect(slide, x, y, w, h, fill, line = "FFFFFF", transparency = 0) {
  addShape(slide, SH.rect, {
    x, y, w, h,
    fill: { color: fill, transparency },
    line: { color: line, transparency: line === "FFFFFF" ? 100 : 0, width: 0.6 },
  });
}

function addRound(slide, x, y, w, h, fill, line = "FFFFFF", transparency = 0) {
  addShape(slide, SH.round, {
    x, y, w, h,
    rectRadius: 0.08,
    fill: { color: fill, transparency },
    line: { color: line, transparency: line === "FFFFFF" ? 100 : 0, width: 0.6 },
  });
}

function addText(slide, text, x, y, w, h, options = {}) {
  slide.addText(text, {
    x, y, w, h,
    fontFace: options.fontFace || "Aptos",
    fontSize: options.fontSize || 16,
    color: options.color || C.ink,
    bold: options.bold || false,
    italic: options.italic || false,
    align: options.align || "left",
    valign: options.valign || "mid",
    margin: options.margin ?? 0.06,
    fit: options.fit || "shrink",
    breakLine: options.breakLine,
    paraSpaceAfterPt: options.paraSpaceAfterPt,
    bullet: options.bullet,
  });
}

function addRich(slide, runs, x, y, w, h, options = {}) {
  slide.addText(runs, {
    x, y, w, h,
    fontFace: options.fontFace || "Aptos",
    fontSize: options.fontSize || 15,
    color: options.color || C.ink,
    bold: options.bold || false,
    valign: options.valign || "top",
    margin: options.margin ?? 0.04,
    fit: "shrink",
    paraSpaceAfterPt: options.paraSpaceAfterPt || 5,
  });
}

function addLine(slide, x1, y1, x2, y2, color = C.line, width = 1.2) {
  addShape(slide, SH.line, {
    x: x1,
    y: y1,
    w: x2 - x1,
    h: y2 - y1,
    line: { color, width, beginArrowType: "none", endArrowType: "none" },
  });
}

function addHeader(slide, title, subtitle, idx, dark = false) {
  const ink = dark ? C.white : C.ink;
  const muted = dark ? "DDE6E0" : C.muted;
  addText(slide, "SIM-KK ARCHITECTURE", 0.64, 0.33, 2.5, 0.24, {
    fontSize: 8.5,
    color: dark ? C.brass : C.green,
    bold: true,
    margin: 0,
  });
  addText(slide, title, 0.62, 0.72, 8.6, 0.72, {
    fontFace: "Trebuchet MS",
    fontSize: 25,
    color: ink,
    bold: true,
    margin: 0,
  });
  if (subtitle) {
    addText(slide, subtitle, 0.64, 1.39, 7.8, 0.38, {
      fontSize: 11.8,
      color: muted,
      margin: 0,
    });
  }
  addText(slide, String(idx).padStart(2, "0"), 12.22, 0.34, 0.5, 0.26, {
    fontSize: 8.5,
    color: muted,
    align: "right",
    margin: 0,
  });
}

function addFooter(slide, idx, dark = false) {
  addText(slide, "Source: ARCHITECTURE.md / CONTEXT.md / DPPL PDF evidence", 0.64, 7.12, 5.8, 0.2, {
    fontSize: 7.4,
    color: dark ? "C8D5D0" : "777D86",
    margin: 0,
  });
  addText(slide, `${idx}/8`, 12.23, 7.12, 0.48, 0.2, {
    fontSize: 7.4,
    color: dark ? "C8D5D0" : "777D86",
    align: "right",
    margin: 0,
  });
}

function addPill(slide, text, x, y, w, color, textColor = C.white) {
  addRound(slide, x, y, w, 0.38, color, color);
  addText(slide, text, x + 0.08, y + 0.075, w - 0.16, 0.16, {
    fontSize: 9.2,
    color: textColor,
    bold: true,
    align: "center",
    margin: 0,
  });
}

function addMiniLabel(slide, text, x, y, w, color = C.green) {
  addText(slide, text, x, y, w, 0.2, {
    fontSize: 8.7,
    color,
    bold: true,
    margin: 0,
  });
}

function addBulletList(slide, items, x, y, w, h, color = C.ink) {
  const runs = items.map((item, i) => ({
    text: item,
    options: { bullet: true, breakLine: i < items.length - 1 },
  }));
  addRich(slide, runs, x, y, w, h, { color, fontSize: 12.6, paraSpaceAfterPt: 6 });
}

function addNode(slide, title, lines, x, y, w, h, fill, accent = C.green) {
  addRect(slide, x, y, w, h, fill, C.line);
  addRect(slide, x, y, 0.07, h, accent, accent);
  addText(slide, title, x + 0.16, y + 0.13, w - 0.28, 0.22, {
    fontSize: 11.7,
    color: C.ink,
    bold: true,
    margin: 0,
  });
  addText(slide, lines.join("\n"), x + 0.16, y + 0.45, w - 0.3, h - 0.52, {
    fontSize: 8.9,
    color: C.muted,
    margin: 0,
    valign: "top",
    fit: "shrink",
  });
}

function slide1() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.green };
  addRect(slide, 0, 0, W, H, C.green, C.green);
  addRect(slide, 0, 0, 0.22, H, C.amber, C.amber);
  addRect(slide, 9.2, 0, 4.2, H, "173F36", "173F36");
  addHeader(slide, "SIM-KK menyatukan kasir, klinik, gudang, dan laporan", "Target arsitektur dari DPPL; belum ada source code aplikasi di workspace.", 1, true);

  addText(slide, "Sistem Informasi Manajemen Klinik Kecantikan", 0.68, 2.12, 5.6, 0.48, {
    fontFace: "Trebuchet MS",
    fontSize: 20,
    color: C.white,
    bold: true,
    margin: 0,
  });
  addText(slide, "Batas penting: deck ini menjelaskan desain target source-backed, bukan status aplikasi yang sudah berjalan.", 0.7, 2.76, 5.3, 0.66, {
    fontSize: 13,
    color: "E5EFEA",
    margin: 0,
    fit: "shrink",
  });

  addRound(slide, 7.15, 2.48, 2.1, 1.18, C.white, C.white);
  addText(slide, "SIM-KK", 7.45, 2.75, 1.5, 0.34, {
    fontFace: "Trebuchet MS",
    fontSize: 23,
    color: C.green,
    bold: true,
    align: "center",
    margin: 0,
  });
  addText(slide, "single operational core", 7.35, 3.15, 1.66, 0.18, {
    fontSize: 8.8,
    color: C.muted,
    align: "center",
    margin: 0,
  });

  const roles = [
    ["Kasir", 6.08, 1.55, C.amber],
    ["Terapis", 9.45, 1.55, C.blue],
    ["Gudang", 6.08, 4.05, C.clay],
    ["Manajer", 9.45, 4.05, C.green2],
  ];
  roles.forEach(([role, x, y, color]) => {
    addLine(slide, x + 0.92, y + 0.3, 8.2, 3.08, "91B2A8", 1.15);
    addPill(slide, role, x, y, 1.8, color);
  });

  const modules = [
    ["POS", "transaksi + receipt"],
    ["Rekam medis", "treatment + foto"],
    ["FIFO", "stok + HPP"],
    ["Reports", "PDF + .xlsx"],
  ];
  modules.forEach(([a, b], i) => {
    const x = 0.72 + i * 2.12;
    addRect(slide, x, 5.55, 1.75, 0.62, "295B4F", "53786F");
    addText(slide, a, x + 0.1, 5.65, 1.55, 0.17, { fontSize: 10.2, color: C.white, bold: true, align: "center", margin: 0 });
    addText(slide, b, x + 0.1, 5.91, 1.55, 0.15, { fontSize: 7.4, color: "D6E4DE", align: "center", margin: 0 });
  });
  addFooter(slide, 1, true);
}

function slide2() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.base };
  addHeader(slide, "Arsitektur target memisahkan UI, bisnis, data, dan media", "Laravel memegang business logic; Vue hanya presentation boundary.", 2);

  const layers = [
    ["Vue.js frontend", "POS cepat, tablet rekam medis, report preview", C.green],
    ["Laravel backend", "auth, role access, POS, commission, FIFO, reports", C.blue],
    ["Relational DB", "users, pasien, transaksi, rekam medis, inventory", C.clay],
    ["S3-compatible storage", "before/after clinical photo objects", C.amber],
  ];
  layers.forEach(([t, d, color], i) => {
    const y = 2.0 + i * 0.9;
    addRect(slide, 0.8, y, 5.2, 0.62, color, color);
    addText(slide, t, 1.08, y + 0.13, 1.8, 0.18, { fontSize: 12, color: C.white, bold: true, margin: 0 });
    addText(slide, d, 3.0, y + 0.13, 2.65, 0.2, { fontSize: 8.8, color: "F3F7F5", margin: 0 });
    if (i < layers.length - 1) addShape(slide, SH.chevron, { x: 3.08, y: y + 0.65, w: 0.28, h: 0.18, rotate: 90, fill: { color: C.muted }, line: { color: C.muted } });
  });

  addRect(slide, 6.75, 1.95, 5.45, 3.85, C.surface, C.line);
  addMiniLabel(slide, "Boundary discipline", 7.05, 2.2, 2.1);
  addText(slide, "Frontend boleh kaya interaksi, tapi kalkulasi komisi, mutasi FIFO, dan akses data tetap di backend.", 7.05, 2.55, 4.75, 0.6, { fontSize: 15, color: C.ink, bold: true, margin: 0, fit: "shrink" });
  addBulletList(slide, [
    "Photo metadata stays in database; binary photo objects stay in S3-compatible storage.",
    "Reports use approved database history, not hand-edited export surfaces.",
    "PDF and Excel generators are dependencies by purpose; packages not chosen yet.",
  ], 7.1, 3.45, 4.65, 1.35);
  addFooter(slide, 2);
}

function slide3() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.base };
  addHeader(slide, "Alur operasional mengunci bukti saat pekerjaan selesai", "Status Lunas adalah titik penting untuk receipt, cash ledger, dan komisi.", 3);

  const steps = [
    ["Login", "role access"],
    ["POS", "cart + patient"],
    ["Terapis", "owner selected"],
    ["Lunas", "commission locked"],
    ["Reports", "PDF / .xlsx"],
  ];
  steps.forEach(([t, d], i) => {
    const x = 0.75 + i * 2.42;
    const color = [C.green, C.blue, C.amber, C.clay, C.green2][i];
    addShape(slide, SH.chevron, {
      x, y: 2.35, w: 2.05, h: 1.18,
      fill: { color },
      line: { color },
    });
    addText(slide, t, x + 0.18, 2.67, 1.42, 0.24, { fontSize: 15.5, color: C.white, bold: true, align: "center", margin: 0 });
    addText(slide, d, x + 0.16, 3.04, 1.46, 0.16, { fontSize: 8.6, color: "F9FAFB", align: "center", margin: 0 });
  });

  addRect(slide, 1.05, 4.55, 11.0, 1.04, C.surface, C.line);
  addText(slide, "Snapshot rule", 1.32, 4.78, 1.25, 0.18, { fontSize: 9, color: C.amber, bold: true, margin: 0 });
  addText(slide, "When transaction status becomes Lunas, the backend records receipt, cash inflow, and permanent therapist commission value.", 2.58, 4.68, 8.7, 0.38, { fontSize: 16, color: C.ink, bold: true, margin: 0 });
  addText(slide, "This keeps payroll/report exports tied to approved transaction history.", 2.6, 5.16, 6.6, 0.18, { fontSize: 9.5, color: C.muted, margin: 0 });
  addFooter(slide, 3);
}

function slide4() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.base };
  addHeader(slide, "Empat domain harus berbagi data yang sama", "Core modules are separate work surfaces, not separate truths.", 4);

  addRect(slide, 5.78, 2.14, 1.78, 2.92, C.green, C.green);
  addShape(slide, SH.oval, { x: 5.78, y: 1.93, w: 1.78, h: 0.42, fill: { color: C.green2 }, line: { color: C.green2 } });
  addShape(slide, SH.oval, { x: 5.78, y: 4.84, w: 1.78, h: 0.42, fill: { color: "17483E" }, line: { color: "17483E" } });
  addText(slide, "Approved\nDatabase\nHistory", 5.98, 2.8, 1.38, 0.78, { fontSize: 16, color: C.white, bold: true, align: "center", margin: 0 });

  const domains = [
    ["POS", ["cart, payment status", "receipt, cash ledger", "therapist selected"], 0.92, 2.02, C.green],
    ["Rekam Medis", ["complaints, actions", "treatment timeline", "photo references"], 8.35, 2.02, C.blue],
    ["Gudang FIFO", ["supplier purchase", "HPP batch basis", "stock mutation"], 0.92, 4.55, C.clay],
    ["Laporan", ["financial PDF", "stock .xlsx", "commission .xlsx"], 8.35, 4.55, C.amber],
  ];
  domains.forEach(([t, lines, x, y, color]) => {
    addNode(slide, t, lines, x, y, 3.55, 1.32, C.surface, color);
    addLine(slide, x + (x < 5 ? 3.55 : 0), y + 0.66, x < 5 ? 5.78 : 7.56, y + 0.66, color, 1.1);
  });
  addText(slide, "Shared data prevents reconciliation drift between cashier, therapist, warehouse, and manager reporting.", 1.02, 6.38, 10.7, 0.3, { fontSize: 13.2, color: C.ink, bold: true, margin: 0 });
  addFooter(slide, 4);
}

function slide5() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.base };
  addHeader(slide, "Data model awal sudah menunjuk titik audit", "Known fields are limited; extra tables need implementation decisions.", 5);

  addNode(slide, "Users", ["username", "password hash", "nama_lengkap", "level role"], 0.8, 1.95, 2.25, 1.2, C.surface, C.green);
  addNode(slide, "Pasien", ["nama, usia, alamat", "nomor_telp", "rekam_medis_id unique"], 0.8, 4.1, 2.25, 1.2, C.surface, C.blue);
  addNode(slide, "Transaksi Detail", ["id_transaksi", "id_produk", "id_terapis", "nilai_komisi snapshot"], 5.35, 2.8, 2.55, 1.42, "FFF8EA", C.amber);
  addNode(slide, "Produk / Layanan", ["service or product", "price basis", "stock link if product"], 9.75, 1.85, 2.42, 1.14, C.surface, C.clay);
  addNode(slide, "Terapis", ["linked user/role", "commission owner", "payroll export"], 9.75, 4.18, 2.42, 1.14, C.surface, C.green2);
  addNode(slide, "Rekam Medis", ["complaints", "actions", "photo media refs"], 4.08, 5.05, 2.38, 1.06, C.surface, C.blue);
  addNode(slide, "Inventory Batch", ["supplier purchase", "HPP", "FIFO order"], 6.95, 5.05, 2.38, 1.06, C.surface, C.clay);

  addLine(slide, 3.05, 2.55, 5.35, 3.18, C.green, 1);
  addLine(slide, 3.05, 4.68, 4.08, 5.48, C.blue, 1);
  addLine(slide, 7.9, 3.32, 9.75, 2.42, C.clay, 1);
  addLine(slide, 7.9, 3.5, 9.75, 4.75, C.green2, 1);
  addLine(slide, 6.6, 4.22, 6.0, 5.05, C.amber, 1);
  addLine(slide, 7.1, 4.22, 7.55, 5.05, C.amber, 1);

  addRect(slide, 4.9, 1.8, 3.15, 0.44, C.green, C.green);
  addText(slide, "Audit focus: immutable commission + FIFO batch evidence", 5.08, 1.93, 2.82, 0.13, { fontSize: 8.8, color: C.white, bold: true, align: "center", margin: 0 });
  addFooter(slide, 5);
}

function slide6() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: "142F2A" };
  addRect(slide, 0, 0, W, H, "142F2A", "142F2A");
  addRect(slide, 0, 0, W, 1.72, C.green, C.green);
  addHeader(slide, "Risiko teknis berada di privacy, audit, dan FIFO", "Unknowns are manageable only if they become explicit engineering decisions.", 6, true);

  const risks = [
    ["Clinical photo privacy", 5, C.clay, "access, retention, audit trail"],
    ["Commission immutability", 4, C.amber, "approved transaction history"],
    ["FIFO stock batches", 4, C.blue, "batch, expiry, HPP mutation"],
    ["Auth/session model", 3, C.green2, "role granularity + middleware"],
    ["Deployment/backups", 3, "D2B06D", "DB/S3 restore policy"],
  ];
  risks.forEach(([name, score, color, note], i) => {
    const y = 2.18 + i * 0.75;
    addText(slide, name, 0.92, y + 0.08, 2.25, 0.2, { fontSize: 11.2, color: C.white, bold: true, margin: 0 });
    addRect(slide, 3.35, y + 0.1, 5.2, 0.22, "254B43", "254B43");
    addRect(slide, 3.35, y + 0.1, score * 0.92, 0.22, color, color);
    addText(slide, note, 8.82, y + 0.07, 3.0, 0.18, { fontSize: 8.7, color: "C7D8D1", margin: 0 });
  });
  addRect(slide, 0.88, 6.25, 10.9, 0.48, "1C433B", "345F55");
  addText(slide, "Do not hide these as implementation details: they change schema, permissions, exports, and rollback planning.", 1.1, 6.39, 10.4, 0.13, { fontSize: 10.6, color: C.white, bold: true, margin: 0 });
  addFooter(slide, 6, true);
}

function slide7() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.base };
  addHeader(slide, "Roadmap implementasi: foundation before polish", "Build the audit path first, then make the prototype operationally rich.", 7);

  const phases = [
    ["01", "Schema + Auth", "roles, DB choice, migrations"],
    ["02", "POS Core", "cart, therapist, Lunas"],
    ["03", "Medical Records", "timeline, S3 photo refs"],
    ["04", "Inventory FIFO", "supplier batches, HPP"],
    ["05", "Reports", "PDF finance, Excel stock/commission"],
  ];
  phases.forEach(([n, t, d], i) => {
    const x = 0.82 + i * 2.44;
    addShape(slide, SH.oval, { x, y: 2.32, w: 0.66, h: 0.66, fill: { color: [C.green, C.blue, C.amber, C.clay, C.green2][i] }, line: { color: [C.green, C.blue, C.amber, C.clay, C.green2][i] } });
    addText(slide, n, x + 0.13, 2.52, 0.4, 0.14, { fontSize: 8.7, color: C.white, bold: true, align: "center", margin: 0 });
    if (i < phases.length - 1) addLine(slide, x + 0.66, 2.65, x + 2.26, 2.65, C.line, 1.6);
    addText(slide, t, x - 0.05, 3.27, 1.62, 0.25, { fontSize: 12.2, color: C.ink, bold: true, align: "center", margin: 0 });
    addText(slide, d, x - 0.22, 3.72, 1.95, 0.48, { fontSize: 8.6, color: C.muted, align: "center", margin: 0, fit: "shrink" });
  });

  addRect(slide, 0.85, 5.25, 11.58, 0.92, C.surface, C.line);
  addMiniLabel(slide, "Verification per phase", 1.12, 5.45, 1.6, C.green);
  addText(slide, "Each phase should produce code-level evidence: tests, migrations, UI smoke checks, and export fixtures before docs claim it is live.", 2.8, 5.35, 8.9, 0.34, { fontSize: 13.4, color: C.ink, bold: true, margin: 0, fit: "shrink" });
  addFooter(slide, 7);
}

function slide8() {
  const slide = pptx.addSlide("SIMKK");
  slide.background = { color: C.green };
  addRect(slide, 0, 0, W, H, C.green, C.green);
  addRect(slide, 9.1, 0, 4.25, H, "173F36", "173F36");
  addHeader(slide, "Arsitektur siap dibangun, bukan diklaim sudah live", "Current workspace is a source-backed design workspace with no app runtime yet.", 8, true);

  addShape(slide, SH.oval, { x: 0.85, y: 2.18, w: 1.0, h: 1.0, fill: { color: C.amber }, line: { color: C.amber } });
  addText(slide, "!", 1.19, 2.46, 0.32, 0.25, { fontFace: "Trebuchet MS", fontSize: 18, color: C.white, bold: true, align: "center", margin: 0 });
  addText(slide, "Evidence boundary", 2.15, 2.2, 3.35, 0.34, { fontFace: "Trebuchet MS", fontSize: 20, color: C.white, bold: true, margin: 0 });
  addText(slide, "The architecture is credible as a target design because it is traced to the DPPL, CONTEXT.md, and ARCHITECTURE.md. It is not proof of deployed software.", 2.18, 2.78, 4.95, 0.7, { fontSize: 12.4, color: "E5EFEA", margin: 0, fit: "shrink" });

  const cols = [
    ["Proven", ["roles and domains", "target stack", "core business rules"], 0.95, C.amber],
    ["Not yet proven", ["source code", "runtime config", "tests/deployment"], 4.68, C.rose],
    ["Next decisions", ["DB engine", "privacy policy", "report libraries"], 8.42, C.brass],
  ];
  cols.forEach(([title, lines, x, color]) => {
    addRect(slide, x, 4.65, 3.1, 1.17, "245A4E", "53786F");
    addText(slide, title, x + 0.18, 4.84, 2.55, 0.2, { fontSize: 12, color, bold: true, margin: 0 });
    addText(slide, lines.join("\n"), x + 0.2, 5.18, 2.62, 0.42, { fontSize: 9.1, color: C.white, margin: 0, fit: "shrink" });
  });
  addFooter(slide, 8, true);
}

[slide1, slide2, slide3, slide4, slide5, slide6, slide7, slide8].forEach(fn => fn());

pptx.writeFile({ fileName: OUT });
console.log(OUT);
