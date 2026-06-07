# prototype/src/ — Vue 3 Frontend

Vue 3 + TypeScript SPA, no router library, no state-management library. Hand-rolled view switcher in `App.vue` driven by `activeView: ViewKey`. Tailwind v4 + CSS tokens. Lucide icons.

## STRUCTURE
```
src/
├── main.ts                 # createApp(App).mount('#app'); imports styles/tokens.css
├── App.vue                 # login state, role state, view switcher, bootstrap fetch
├── vite-env.d.ts
├── components/             # 11 reusable widgets (see sub-table)
├── views/                  # 5 page-level views, one per role + Login
├── services/
│   └── api.ts              # only HTTP client; wraps fetch + bearer + JSON parse
├── utils/
│   ├── access.ts           # roleProfiles + canOpenView — RBAC map (frontend)
│   └── format.ts           # rupiah() — Intl IDR formatter
├── types/
│   └── domain.ts           # Role, ViewKey, User, Patient, ServiceItem, etc.
└── styles/
    └── tokens.css          # CSS variables + global classes (1276 lines — design system)
```

### Components
| File | Used by | Purpose |
|---|---|---|
| `AppShell.vue` | `App.vue` | sidebar nav, topbar, role-scope chip, search input |
| `RoleSwitcher.vue` | login | demo role buttons |
| `SegmentedControl.vue` | POS, reports | category/report selector |
| `ActionDrawer.vue` | inventory | "barang masuk" side drawer |
| `DataTable.vue` | generic | sortable table primitive |
| `MetricStrip.vue` | app shell | 4-column metric cards |
| `PaymentSummary.vue` | POS | cart, discount, payment method, pay button |
| `PhotoCompare.vue` | medical | before/after photo grid |
| `Timeline.vue` | medical | chronological treatment history |
| `ReportPreview.vue` | reports | PDF/XLSX preview cards |
| `ExportToast.vue` | POS, reports | bottom-right success toast |

### Views
| File | Role | Default view for |
|---|---|---|
| `LoginView.vue` | — (public) | always first |
| `PosView.vue` | Kasir | Kasir |
| `MedicalRecordView.vue` | Terapis | Terapis |
| `InventoryView.vue` | Gudang | Gudang |
| `ReportsView.vue` | Manajer | Manajer (sees all 4) |

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Add a new view | `views/<Name>View.vue` + register in `App.vue` `activeComponent` map + add to `types/domain.ts` `ViewKey` + add `RoleProfile` in `utils/access.ts` | no router; pure ref switch |
| Change role permissions | `utils/access.ts` `roleProfiles` + mirror in `backend/app.mjs` `requireRole(...)` | both sides must match |
| Add API call | `services/api.ts` (typed wrapper) | all responses funneled through `parseJson` |
| Add money field | use `rupiah` from `utils/format.ts` | never write `Intl.NumberFormat` inline |
| Change colors / spacing | `styles/tokens.css` (CSS vars) + `tailwind.config.ts` (palette tokens) | both must stay in sync |
| Add domain type | `types/domain.ts` | single source of TS types |
| Add new testid attribute | add `data-testid="..."` on element + mirror in `tests/smoke.spec.ts` | smoke tests rely on these |

## CONVENTIONS (in addition to root)
- **Composition API + `<script setup lang="ts">` only.** No Options API.
- **TypeScript strict** is enforced by `npm run build` (`vue-tsc --noEmit`).
- **Props via `defineProps<T>()` with explicit interface; events via `defineEmits<{ name: [args] }>()`.** No `Prop<T>` style.
- **Service layer is the only place that calls `fetch()`.** Components call typed functions from `services/api.ts`; they never construct URLs.
- **All money values are integers (IDR);** format with `rupiah()`.
- **Indonesian UI copy** ("Lunas", "Komisi terkunci", "Belum terkunci", "Barang masuk", "Snapshot tersimpan permanen saat status Lunas"). Keep new strings consistent with this voice.
- **Search input** is owned by `AppShell.vue`; pass `searchQuery` as a prop into views and use it in their `filteredX` computed.
- **CSS class naming is BEM-ish** (`.service-tile`, `.patient-facts`, `.batch-row.first`) inside `styles/tokens.css`. Components in `.vue` files use scoped styles minimally — most styling lives in tokens.css.
- **testids use kebab-case**: `data-testid="service-card-1"`, `data-testid="therapist-select"`, `data-testid="pay-button"`. Smoke tests rely on these.

## ANTI-PATTERNS
- **NEVER** add a new view without also updating `utils/access.ts` and `types/domain.ts`. View is unreachable from `App.vue` otherwise.
- **NEVER** call `fetch()` from a Vue component. Use `services/api.ts` (keeps `Authorization` header logic in one place).
- **NEVER** store the bearer token in `localStorage` or `sessionStorage`. It lives only in the in-memory `token` ref in `App.vue`; the user re-logs in on refresh — that's acceptable for the local demo.
- **NEVER** use `as any`, `@ts-ignore`, `@ts-expect-error`. (project-wide rule)
- **NEVER** switch to vue-router without first checking `ARCHITECTURE.md` — the hand-rolled view switcher is intentional and removes a dependency.
- **NEVER** store a `Commission` value client-side that the server didn't write. Commission is server-derived at `Lunas`; the frontend only displays.
- **NEVER** rely on `localStorage` for clinical photos. The base64 dataURL preview is in-memory only; the actual file goes to the backend `storage/` adapter.
- **NEVER** animate behind dense text in the POS or report panels. Motion must not block cashier speed. (design spec rule)
- **NEVER** create "nested cards" in section layouts. Sections use flat grid layouts.

## UNIQUE STYLES
- **No `vue-router`**, no `pinia`, no `vuex`. Just `ref`/`computed` in `App.vue` driving a `<component :is="activeComponent">`.
- **Role-gated navigation** is enforced twice: client-side via `canOpenView()` (visibility of nav buttons), server-side via `requireRole()` in `backend/app.mjs`. Don't remove either layer.
- **POS layout** = 3-column grid (`.pos-grid`): catalog (left, 1.25fr) / patient context (mid, 0.8fr) / payment (right, 0.72fr). Therapist select is mandatory before `markPaid()` runs.
- **Tablet/medical-friendly** input sizing: min-height 42px on action buttons, 16px on consent checkboxes.
- **CSS animation tokens**: `--duration-fast: 180ms`, `--duration-panel: 280ms`, `--duration-reveal: 700ms`, `--ease-premium: cubic-bezier(0.2, 0.8, 0.2, 1)`. Use these, not raw values.
- **Brand color** `clinical #1F5F50` (sage green) is the primary action color. Accent `amber #B98948`. Use `tokens.css` CSS vars in inline styles; use Tailwind palette tokens in class-based styles.
- **Typography**: `Instrument Sans` body, `Instrument Serif` display (loaded via Google Fonts in `tokens.css`).
- **`tokens.css` is 1276 lines** — it's a deliberate design system, not dead CSS. Read it before changing visual rhythm.

## COMMANDS
Run from `prototype/`:
```bash
npm run dev:web       # vite dev (127.0.0.1:5173)
npm run build         # vue-tsc --noEmit && vite build → dist/
npm run preview       # serve built dist
npm run test:smoke    # playwright
```

## NOTES
- **`PhotoCompare.vue` shows gradient placeholders** for clinical photos in the prototype, not the actual uploaded image bytes. Real image bytes live in `storage/clinical/<RM-id>/` on disk; the DB stores only the `local://...` reference. (`backend/storage.mjs`)
- **`PaymentSummary.vue` is the largest component** (~4.7 KB) — owns cart math, discount clamp, payment method, commission display, pay button.
- **`AppShell.vue`** is the second largest (~3.3 KB) — owns nav rail, search, role-scope chip, topbar.
- **Role context is shared via props** from `App.vue` to all views (`token`, `patients`, `services`, `therapists`, `transactions`, `inventory`, `reports`, `searchQuery`). Not via provide/inject.
- **`searchQuery` is a global command-bar search** wired through `AppShell`. Each view implements its own `filteredX` computeds; do not centralize this logic.
