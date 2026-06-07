# prototype/backend/ — Node + Express API

Node 22 + Express 5 + sql.js (WASM SQLite) + PDFKit + ExcelJS. Pure ESM `.mjs` — no TypeScript. All routes defined inline in `app.mjs` (no `routes/` folder). Bearer-token auth + `requireRole` guard.

## STRUCTURE
```
backend/
├── server.mjs          # bootstrap: createApp().listen(5174, "127.0.0.1")
├── app.mjs             # Express factory: middleware, auth, all routes, error handler
├── database.mjs        # sql.js open/migrate/seed + business rules (650 lines)
├── seed.mjs            # scryptSync password hash + demo data (users/patients/services/...)
├── reporting.mjs       # PDFKit (finance PDF) + ExcelJS (stock + komisi XLSX)
├── storage.mjs         # local clinical photo object store (15 lines)
└── tests/
    └── api.test.mjs    # node:test, isolated tempdir per run, 4 test cases
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Add a new route | `app.mjs` | mount in `createApp()`; chain `auth, requireRole(...)` |
| Add a new role guard | `app.mjs:38` `requireRole(...roles)` | wrap write routes; `/api/reports/*/export` is `Manajer`-only |
| Add a new table | `database.mjs` `migrate()` block (CREATE TABLE IF NOT EXISTS) | use additive `ALTER TABLE` for existing DBs |
| Add seed data | `seed.mjs` | wipe `data/simkk.sqlite` to re-seed |
| Change FIFO logic | `database.mjs` `decrementStock()` + `getInventory()` query | FIFO is a hard business rule; don't change sort key casually |
| Change commission formula | `database.mjs` `createPaidTransaction()` | commission is snapshotted on `Lunas` — changing the formula only affects new transactions |
| Change PDF letterhead | `reporting.mjs` `createFinancePdf()` | clinic name + Samarinda address hard-coded |
| Change export columns | `reporting.mjs` `createWorkbook()` | reads `Object.keys(report.rows[0])` |
| Add a clinical-photo path | `storage.mjs` `storeClinicalPhoto()` | returns `local://clinical/<recordId>/<uuid>-<safeName>` |
| Update role→file map | mirror in `prototype/src/utils/access.ts` | frontend `roleProfiles.allowedViews` must match |

## CONVENTIONS (in addition to root)
- **Pure ESM**: `"type": "module"` at root; every file is `.mjs` with explicit `.mjs` imports.
- **No TypeScript on backend.** If you need types, derive them from `prototype/src/types/domain.ts` (the canonical source).
- **`export async function createApp(options)`** pattern: every module is a factory returning a closed-over state. Allows the API test (`api.test.mjs`) to instantiate against a tempdir.
- **Bearer token format**: `Authorization: Bearer simkk_<uuid_no_hyphens>`. Parsed via `request.headers.authorization?.replace(/^Bearer\s+/i, "")`. Tokens persist in `sessions` table.
- **Password hash is `scryptSync(password, "simkk-local-salt", 32).toString("hex")`**, not bcrypt/argon2. **Local-only** — not safe for production. (`seed.mjs:3`)
- **All money is integer IDR**; format with the exported `rupiah` helper.
- **Error contract**: throw `Object.assign(new Error("..."), { status: 4xx })`. The error middleware at `app.mjs:119` returns `{ message }` with `error.status || 500`.
- **Routes are in `app.mjs` only.** No `routes/` directory. If a route gets non-trivial, split a service file (see `reporting.mjs`, `storage.mjs`).

## ANTI-PATTERNS
- **NEVER** add a route without `auth` middleware (or explicitly opt out for `/api/login`, `/api/health`).
- **NEVER** add a write route without `requireRole(...)`. Read-only bootstrap (`GET /api/bootstrap`) is the only auth-only endpoint.
- **NEVER** store clinical photo bytes in a SQL column. Use `storage.mjs` and store the returned URI only.
- **NEVER** recompute commission from current rate after `Lunas`. `transactions.commission` and `transaction_items.commission` are the audit record.
- **NEVER** change the FIFO sort key without re-running `api.test.mjs`. The current key (`expiry ASC, id ASC`, with `"Reusable"` sentinel mapped to `9999-12-31`) is part of the business contract.
- **NEVER** mutate `transactions` row after it becomes `Lunas` — treat as immutable.
- **NEVER** read `request.user.role` without first going through `auth` middleware.
- **NEVER** store secrets in source. `process.env.SIMKK_API_PORT` and `process.env.VERCEL` are the only env vars read.

## UNIQUE STYLES
- **sql.js (WASM SQLite)** instead of better-sqlite3. WASM resolved at `node_modules/sql.js/dist/sql-wasm.wasm` via `locateFile`. (`database.mjs:9`)
- **Persistence is `writeFile(dbPath, Buffer.from(db.export()))` after every write.** Full export on every commit. No incremental WAL. Don't try to add a write-queue; the prototype keeps it dumb.
- **Vercel fallback** (`process.env.VERCEL=1`): DB file goes to `os.tmpdir()/simkk.sqlite`; clinical files go to `os.tmpdir()/simkk-storage`. Wiped on serverless recycle. Demo only.
- **Inline migrations** + `CREATE TABLE IF NOT EXISTS` + additive `PRAGMA table_info` → `ALTER TABLE ADD COLUMN` for schema changes. No migration tool. (`database.mjs:185-188`)
- **Inline seed**: `seedIfNeeded()` runs once on `openDatabase()` if `users` table is empty. To re-seed, delete `data/simkk.sqlite`.
- **Receipt ID = `RCPT-${transactionId}`**; transaction ID format = `TRX-YYMMDD-NNN` (year+month+day from ISO slice `2,10`). (`database.mjs:511-513`)
- **Stock status thresholds hard-coded** in `statusForBatches()`: `Prioritas` if first batch ≤ `2026-07-31`; else `Menipis` (≤ 12) / `Aman`.
- **Therapist base salary hard-coded `2500000`** in commission report. Change here only.
- **`decrementStock()` throws `status: 409`** on insufficient FIFO stock; the error middleware surfaces this.
- **Test isolation**: `api.test.mjs` uses `mkdtemp(join(tmpdir(), "simkk-api-"))` and instantiates `createApp({ dbPath, storageRoot })` per run. No global state. (`api.test.mjs:38-48`)

## API ROUTES
| Method | Path | Roles | Returns |
|---|---|---|---|
| `GET` | `/api/health` | — | `{ ok: true }` |
| `POST` | `/api/login` | — | `{ token, user }` |
| `GET` | `/api/bootstrap` | any auth | users/patients/services/therapists/transactions/inventory/reports |
| `POST` | `/api/transactions/pay` | Kasir, Manajer | `{ transaction, receipt, cashLedger }` (status 201) |
| `POST` | `/api/patients/:id/treatments` | Terapis, Manajer | new treatment note (201) |
| `POST` | `/api/patients/:id/photos` | Terapis, Manajer | `{ id, label, date, objectRef }` (201) |
| `POST` | `/api/inventory/purchases` | Gudang, Manajer | updated inventory product (201) |
| `GET` | `/api/reports/:id/export` | Manajer | `application/pdf` or `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |

## COMMANDS
Run from `prototype/`:
```bash
npm run dev:api       # node backend/server.mjs
npm run test:api      # node --test backend/tests/*.test.mjs
```

## NOTES
- **`database.mjs` is 650 lines** — it owns schema, migrations, seed-orchestration, and all CRUD business rules. Splitting it is a refactor; the prototype keeps it monolithic for clarity.
- **`reporting.mjs` returns a `Buffer`** for both PDF and XLSX. The route handler at `app.mjs:100-117` sets `content-type` + `content-disposition` and streams via `response.send(buffer)`.
- **PDF finance report** has hard-coded column x-coords (48/190/300/410/545) and the clinic letterhead baked in. To re-brand, change `reporting.mjs:11-12` and adjust column x-coords.
- **`storage.mjs` sanitizer** replaces `[^a-zA-Z0-9._-]` with `-`. UUID prefix prevents collisions. Filename max length is whatever Node accepts; no explicit cap.
- **API test (`api.test.mjs`)** boots Express in-process on port 0 (random), logs in as 4 roles, then exercises POS / medical / inventory / report-export flows. It explicitly asserts role-rejection (403) for cross-role writes. Add a new `it(...)` block for each new route.
- **Seed login credentials** (password `simkk-2026` for all): `kasir`, `terapis`, `gudang`, `manajer`. (`seed.mjs:6-11`)
