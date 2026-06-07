# prototype/ — App Root

Single-package, two-runtime layout: Vue 3 frontend + Node/Express backend + Vercel serverless adapter. Glued with `concurrently`. No monorepo tooling (no pnpm workspaces, no turbo, no lerna).

## STRUCTURE
```
prototype/
├── package.json                # type: module, scripts, deps (one root only)
├── tsconfig.json               # frontend-only TS strict, Bundler resolution
├── vite.config.ts              # port 5173, /api proxied to :5174
├── tailwind.config.ts          # custom palette tokens (see parent AGENTS.md)
├── playwright.config.ts        # baseURL 5173, auto-runs `npm run dev`, chromium only
├── index.html                  # Vite entry
├── .vercelignore, .gitignore
├── api/
│   └── [...path].mjs           # Vercel serverless adapter; reuses backend/app.mjs createApp()
├── backend/                    # Node + Express + sql.js (see prototype/backend/AGENTS.md)
├── src/                        # Vue 3 frontend (see prototype/src/AGENTS.md)
├── data/
│   └── simkk.sqlite            # runtime DB file (regenerated from seed if absent)
├── storage/
│   └── clinical/               # local clinical object storage (stand-in for S3)
├── tests/
│   └── smoke.spec.ts           # Playwright E2E (login, POS, photo, report export, viewport)
├── test-results/               # Playwright output
├── dist/                       # Vite build output (no backend dist)
└── .vercel/                    # Vercel project cache
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Add script | `package.json` | follow existing pattern: `dev` / `dev:api` / `dev:web` / `build` / `test:api` / `test:smoke` |
| Change ports / proxy | `vite.config.ts` | both ports hard-coded: Vite 5173, API 5174 |
| Add env var | edit `backend/server.mjs` (`SIMKK_API_PORT`) or `backend/database.mjs` (`VERCEL`) — no `.env` file exists |
| Add a tailwind token | `tailwind.config.ts` + `src/styles/tokens.css` (CSS var) | keep both in sync |
| Seed data tweaks | `backend/seed.mjs` | wipe `data/simkk.sqlite` to re-seed |

## CONVENTIONS (in addition to root)
- **No gitignore for `data/`, `storage/`, `dist/`** beyond defaults. Local DB and clinical files persist between runs.
- **`api/[...path].mjs` is a Vercel adapter only.** Do not call its handler from local code; it's a thin re-export of `createApp()` from `backend/app.mjs`.
- **Vite proxy** maps `/api/*` to `http://127.0.0.1:5174`. Frontend never hard-codes the API port — always use relative `/api` paths via `src/services/api.ts`.

## ANTI-PATTERNS
- **NEVER** add a second `package.json` (frontend/ or backend/). The single-root layout is intentional.
- **NEVER** commit `data/simkk.sqlite` or `storage/clinical/` to a public repo — both contain clinic operational data.
- **NEVER** switch the dev port (5173/5174) without updating all 4 files: `vite.config.ts`, `playwright.config.ts`, `backend/server.mjs`, and the smoke spec `getByTestId` selectors.
- **NEVER** add a route handler directly in `vite.config.ts` or in a Vue file — routes live in `backend/app.mjs`.
- **NEVER** assume the Vercel `/tmp` SQLite is durable. Treat any Vercel deploy as a demo, not a system of record.

## COMMANDS
```bash
# From prototype/ only
npm install
npm run dev           # API + Vite
npm run dev:api       # backend only (127.0.0.1:5174)
npm run dev:web       # frontend only (127.0.0.1:5173)
npm run build         # vue-tsc --noEmit && vite build
npm run preview       # serve built dist
npm run test:api      # node --test backend/tests/*.test.mjs
npm run test:smoke    # playwright (chromium only)
```

## NOTES
- **`overrides` block** in `package.json` pins `exceljs → uuid ^11.1.1` — required for current Node + ExcelJS compat; do not remove without re-running smoke tests.
- **`concurrently -k -n API,WEB`** in `dev` script forces both processes to be killed on Ctrl+C. Don't split them into separate terminals unless debugging one in isolation.
- **Vite dev `host: "127.0.0.1"`** is hard-coded (not `localhost`) to avoid IPv6/IPv4 ambiguity on Windows.
- **Storage paths** under `storage/clinical/` follow the URI pattern `local://clinical/<recordId>/<uuid>-<safeName>`. Sanitizer: replace `[^a-zA-Z0-9._-]` with `-`. See `backend/storage.mjs`.
