# PROJECT KNOWLEDGE BASE — SIM-KK

**Generated:** 2026-06-01
**Repo:** Sistem Informasi Manajemen Klinik Kecantikan
**Source spec:** `C:\Users\stefa\Downloads\Rancangan Sistem Informasi Klinik Kecantikan.pdf`
**Project status (verified):** Laravel + Vue apps under `apps/api` and `apps/web`. Dev uses SQLite; production target is Cloudflare D1. Telegram (not WhatsApp) chosen for patient notifications. See `ARCHITECTURE.md` and `HALLUCINATION.md` for verified-vs-claimed boundaries.

## OVERVIEW
Vue 3 frontend + local Node/Express backend with sql.js (WASM SQLite) persistence. Single beauty-clinic POS + rekam medis + gudang (FIFO) + manajer reports slice, ready for the Vercel-demo deploy path. Production target stack (Laravel + PostgreSQL/MySQL + S3) is **not** implemented — only documented in `ARCHITECTURE.md`.

## STRUCTURE
```
SIM-KK/
├── ARCHITECTURE.md          # system architecture, source of truth on verified state
├── CONTEXT.md               # product spec (target users, business rules, constraints)
├── HALLUCINATION.md         # anti-fabrication log: what was inferred vs verified
├── docs/
│   ├── EXTERNAL-DEPENDENCIES.md  # PRD→3rd-party API map (Telegram, S3, hosting)
│   └── superpowers/
│       ├── plans/                # implementation plans
│       └── specs/                # design specs
├── scripts/
│   └── create-architecture-deck.cjs  # builds the PPTX deck in outputs/
├── outputs/                 # build artifacts, screenshots, dev-server logs, deck preview
└── prototype/               # see prototype/AGENTS.md
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Product spec / role definitions | `CONTEXT.md` | verified vs inferred clearly marked |
| Architecture baseline | `ARCHITECTURE.md` | "verified locally" vs "source-backed target" |
| Hallucination guardrails | `HALLUCINATION.md` | log of assumptions + verification per work item |
| External API dependencies | `docs/EXTERNAL-DEPENDENCIES.md` | Telegram, S3, hosting choices, costs |
| Run / verify / scope | `prototype/README.md` | seed login, scripts, "remaining production gaps" |
| App code | `prototype/src/` and `prototype/backend/` | see sub-AGENTS.md |
| Design plans/specs | `docs/superpowers/{plans,specs}/` | dated 2026-05-25 slices |

## CODE MAP (cross-system)
| Concern | Frontend | Backend | DB / Storage |
|---|---|---|---|
| Auth + role gate | `src/utils/access.ts` | `backend/app.mjs` (bearer middleware) | `users`, `sessions` tables |
| Kasir POS | `src/views/PosView.vue` | `POST /api/transactions/pay` | `transactions`, `transaction_items`, `cash_ledger`, `inventory_batches` (FIFO) |
| Terapis Rekam Medis | `src/views/MedicalRecordView.vue` | `POST /api/patients/:id/treatments`, `POST /api/patients/:id/photos` | `treatment_notes`, `clinical_photos`, `storage/clinical/<RM-id>/` |
| Gudang Inventory | `src/views/InventoryView.vue` | `POST /api/inventory/purchases` | `inventory_products`, `inventory_batches`, `supplier_purchases` |
| Manajer Reports | `src/views/ReportsView.vue` | `GET /api/reports/:id/export` | `cash_ledger` (PDF) + derived FIFO + komisi (XLSX) |

## CONVENTIONS (project-wide)
- **Indonesian wording in UI**: "Lunas", "Tunai", "Komisi", "Barang masuk", "Rekam medis". Domain copy is Indonesian — keep new strings consistent.
- **IDR integers, no decimals**: every money value is an integer; format client+server with `Intl.NumberFormat("id-ID", { currency: "IDR", maximumFractionDigits: 0 })`. Helper exported as `rupiah` in both `src/utils/format.ts` and `backend/database.mjs`.
- **Date format**: `id-ID` locale, with `.replace(".", "")` to strip the abbreviation dot (e.g. "25 Mei 2026" not "Mei. 2026").
- **Clinical photos MUST NOT be stored in DB rows.** Use `local://clinical/<recordId>/<uuid>-<safeName>` URI in DB + actual file in `prototype/storage/clinical/<RM-id>/`. (`docs/EXTERNAL-DEPENDENCIES.md:59`, `CONTEXT.md` Operational Notes)
- **Commission snapshot is immutable.** Written once on `Lunas` finalization into `transactions.commission` and `transaction_items.commission`. Never recompute on read. (`CONTEXT.md:77,109`)
- **FIFO is a hard business rule.** Sort key: `ORDER BY CASE WHEN expiry = 'Reusable' THEN '9999-12-31' ELSE expiry END ASC, id ASC`. Sentinel `"Reusable"` sorts last. (`CONTEXT.md:93,111`; `backend/database.mjs`)
- **POS must include direct therapist selection to lock commission ownership** before closing transaction. (`CONTEXT.md:130`; enforced in `PosView.vue` + `createPaidTransaction` server-side)
- **Therapist base salary is hard-coded `2500000`** in `backend/database.mjs` — change here only, then re-derive commission grand-total.

## ANTI-PATTERNS (THIS PROJECT)
- **NEVER** store clinical photo bytes inside SQL rows. Use filesystem or S3, store only the object reference. (`docs/EXTERNAL-DEPENDENCIES.md:59`)
- **NEVER** recompute commission from current rate after `Lunas`. Snapshot is the audit record. (`CONTEXT.md:77,109`)
- **NEVER** mark items as production-ready: Laravel app, Cloudflare D1, external S3, Telegram, production deployment, payroll approval policy are **not** fully verified in prod. (`HALLUCINATION.md` + `CONTEXT.md:19,143-151`)
- **NEVER** skip the role guard in `backend/app.mjs`. `requireRole(...)` must be applied to every write route.
- **NEVER** claim "production-grade" persistence when running on Vercel — the `/tmp` SQLite + clinical photo fallback resets on serverless recycle. (`HALLUCINATION.md:2026-05-26`)
- **NEVER** treat `Inferred` items in `CONTEXT.md` (L33, L34, L47) as acceptance criteria. They are design intent, not verified claims.
- **NEVER** call `as any`, `@ts-ignore`, `@ts-expect-error` in TypeScript. (project-wide type safety rule)
- **NEVER** ship empty `catch(e) {}` blocks. (project-wide error handling rule)

## UNIQUE STYLES
- **Single-package two-runtime layout** under `prototype/`: one `package.json` serves Vue 3 frontend and Node/Express backend, glued with `concurrently`. No monorepo tooling.
- **No router library**: hand-rolled view switch in `src/App.vue` using a `ViewKey` ref (`"pos" | "medical" | "inventory" | "reports"`) and `canOpenView(role, view)` from `utils/access.ts`.
- **No frontend type-check on backend**: backend is pure ESM `.mjs` with zero TypeScript. Frontend tsconfig (`strict: true`, `Bundler` resolution) only covers `src/**` and `tests/**`.
- **No `.env*` files exist**: only env vars read are `SIMKK_API_PORT` (server.mjs) and `VERCEL` (database.mjs, switches DB path to `os.tmpdir()`).
- **No lint/format tools**: no `.eslintrc*`, no `.prettierrc*`, no `.editorconfig`. TypeScript strict mode is the only style gate.
- **Seed login** (all 4 roles, password `simkk-2026`): `kasir`, `terapis`, `gudang`, `manajer`.
- **Stock status threshold is hard-coded**: `Prioritas` if earliest batch expiry ≤ `2026-07-31`; else `Menipis` (total ≤ 12) / `Aman`. (`backend/database.mjs`)
- **IDR payment method default** is `Tunai`; max length 32 chars. (`backend/database.mjs:507`)

## COMMANDS (run from `prototype/`)
```bash
npm install
npm run dev           # API :5174 + Vite :5173 via concurrently
npm run dev:api       # backend only
npm run dev:web       # frontend only
npm run build         # vue-tsc --noEmit && vite build
npm run test:api      # node --test backend/tests/*.test.mjs
npm run test:smoke    # playwright (auto-starts `npm run dev` via webServer)
```

## NOTES / GOTCHAS
- **Workspace is NOT a git repository** at the time of writing. `git` is initialized by the harness but no remote/tracked branch exists.
- **Local SQLite is the live database** at `prototype/data/simkk.sqlite` — back it up before any schema or seed change. Auto-regenerated from `seed.mjs` if absent.
- **Clinical photos on disk** live under `prototype/storage/clinical/<recordId>/` — wipe together with their DB rows in `clinical_photos`.
- **Vercel deploy** is a live demo only (`prototype-blush-three.vercel.app` per HALLUCINATION.md 2026-05-26). Don't store real PHI there.
- **Telegram bot token, S3, payroll approval** are open gaps — see `docs/EXTERNAL-DEPENDENCIES.md` and `ARCHITECTURE.md` "Unknowns" for the open decision list.
- **Bugs/issue work** flows through `cs-issue-report` → `cs-issue-analyze` → `cs-issue-fix`. Don't edit code without a `{slug}-report.md` + analyzed root cause.
- **New features** flow through `cs-feat` → `cs-feat-design` → `cs-feat-impl` → `cs-feat-accept`. Do not skip the design step for non-trivial work.
