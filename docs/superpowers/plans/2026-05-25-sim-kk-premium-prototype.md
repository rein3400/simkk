# SIM-KK Premium Prototype Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a frontend-only SIM-KK prototype with clean, structured, premium operational UI for cashier, medical record, inventory, and manager reporting workflows.

**Architecture:** Create a Vue 3 + TypeScript + Vite app under `prototype/`. Use local fixture data, typed view models, route-like local state, and focused components. Keep backend, database, S3, WhatsApp, PDF, and Excel behavior mocked and clearly labeled.

**Tech Stack:** Vue 3, TypeScript, Vite, Tailwind CSS, Motion One or CSS transitions, Lucide icons, local JSON/TypeScript fixtures, Playwright for visual smoke checks.

---

## File Structure

- Create: `prototype/package.json` for scripts and dependencies.
- Create: `prototype/index.html` for Vite entry.
- Create: `prototype/vite.config.ts` for Vue setup.
- Create: `prototype/tsconfig.json` for TypeScript.
- Create: `prototype/tailwind.config.ts` for tokens.
- Create: `prototype/src/main.ts` for app bootstrap.
- Create: `prototype/src/App.vue` for shell and view switching.
- Create: `prototype/src/styles/tokens.css` for palette, typography, spacing, shadows, and motion tokens.
- Create: `prototype/src/data/fixtures.ts` for users, patients, services, transactions, inventory, and reports.
- Create: `prototype/src/types/domain.ts` for role and workflow types.
- Create: `prototype/src/components/` for reusable shell, controls, table, drawer, timeline, report, and toast components.
- Create: `prototype/src/views/` for `LoginView.vue`, `PosView.vue`, `MedicalRecordView.vue`, `InventoryView.vue`, and `ReportsView.vue`.
- Create: `prototype/tests/smoke.spec.ts` for route, interaction, and responsive checks.
- Create: `prototype/README.md` for run and verification notes.

## Task 1: Scaffold Prototype App

**Files:**
- Create: `prototype/package.json`
- Create: `prototype/index.html`
- Create: `prototype/vite.config.ts`
- Create: `prototype/tsconfig.json`
- Create: `prototype/src/main.ts`
- Create: `prototype/src/App.vue`

- [ ] **Step 1: Create package manifest**

Create `prototype/package.json` with scripts:

```json
{
  "scripts": {
    "dev": "vite --host 127.0.0.1",
    "build": "vite build",
    "preview": "vite preview --host 127.0.0.1",
    "test:smoke": "playwright test"
  },
  "dependencies": {
    "@vitejs/plugin-vue": "latest",
    "vite": "latest",
    "typescript": "latest",
    "vue": "latest",
    "lucide-vue-next": "latest",
    "motion": "latest",
    "tailwindcss": "latest",
    "@tailwindcss/vite": "latest"
  },
  "devDependencies": {
    "@playwright/test": "latest"
  }
}
```

- [ ] **Step 2: Create minimal Vue entry**

Create `prototype/src/main.ts`:

```ts
import { createApp } from "vue";
import App from "./App.vue";
import "./styles/tokens.css";

createApp(App).mount("#app");
```

- [ ] **Step 3: Verify scaffold**

Run:

```powershell
cd prototype
npm install
npm run build
```

Expected: Vite build exits 0 and creates `prototype/dist/`.

## Task 2: Define Design Tokens

**Files:**
- Create: `prototype/src/styles/tokens.css`
- Modify: `prototype/src/App.vue`

- [ ] **Step 1: Add palette and type tokens**

Use:
- Base `#FAFAF7`
- Surface `#EEF1EE`
- Ink `#111827`
- Muted `#667085`
- Accent `#1F5F50`
- Micro accent `#B98948`

- [ ] **Step 2: Add motion tokens**

Define CSS variables:
- `--ease-premium: cubic-bezier(.2,.8,.2,1)`
- `--duration-fast: 180ms`
- `--duration-panel: 280ms`
- `--duration-reveal: 700ms`

- [ ] **Step 3: Verify token use**

Run:

```powershell
npm run build
```

Expected: CSS compiles and App renders without missing stylesheet errors.

## Task 3: Add Typed Fixture Data

**Files:**
- Create: `prototype/src/types/domain.ts`
- Create: `prototype/src/data/fixtures.ts`

- [ ] **Step 1: Define domain types**

Include:
- `Role = "Kasir" | "Terapis" | "Gudang" | "Manajer"`
- `Patient`
- `ServiceItem`
- `Therapist`
- `Transaction`
- `InventoryBatch`
- `ReportPreview`

- [ ] **Step 2: Add realistic Indonesian fixtures**

Minimum fixture counts:
- 4 users.
- 6 patients.
- 10 services/products.
- 4 therapists.
- 3 transactions.
- 8 inventory products with FIFO batches.
- 3 report previews.

- [ ] **Step 3: Verify type safety**

Run:

```powershell
npm run build
```

Expected: no TypeScript errors from fixture imports.

## Task 4: Build App Shell

**Files:**
- Create: `prototype/src/components/AppShell.vue`
- Create: `prototype/src/components/RoleSwitcher.vue`
- Create: `prototype/src/components/MetricStrip.vue`
- Modify: `prototype/src/App.vue`

- [ ] **Step 1: Implement shell layout**

Use a fixed top bar, left navigation rail, main workspace, and compact status area. Primary nav labels: `Kasir`, `Rekam Medis`, `Gudang`, `Laporan`.

- [ ] **Step 2: Add role switcher**

The role switcher changes visible role context for prototype inspection. It must not imply real authentication.

- [ ] **Step 3: Verify no card soup**

Inspect the shell visually. Repeated panels may use subtle boundaries, but page sections must not look like nested cards.

## Task 5: Build Login View

**Files:**
- Create: `prototype/src/views/LoginView.vue`
- Modify: `prototype/src/App.vue`

- [ ] **Step 1: Create split login screen**

Left side: SIM-KK identity, clinic operations proof line, quiet animated background. Right side: username/password fields, role demo chips, login button.

- [ ] **Step 2: Add demo login state**

Clicking a role chip sets demo role. Clicking login enters the app shell.

- [ ] **Step 3: Verify accessibility**

Run keyboard tab through fields, role chips, and login button. Expected: focus order is visible and logical.

## Task 6: Build Kasir POS View

**Files:**
- Create: `prototype/src/views/PosView.vue`
- Create: `prototype/src/components/SegmentedControl.vue`
- Create: `prototype/src/components/PaymentSummary.vue`
- Create: `prototype/src/components/ExportToast.vue`

- [ ] **Step 1: Layout POS zones**

Create left catalog, center patient/service context, and right billing cart. Top strip shows shift status, queue count, daily revenue, and pending commission.

- [ ] **Step 2: Add cart interaction**

Clicking a service/product adds it to the cart with a slide-in transition.

- [ ] **Step 3: Add therapist requirement**

Disable `Tandai Lunas` until a therapist is selected.

- [ ] **Step 4: Add paid success state**

After `Tandai Lunas`, show locked commission snapshot, receipt status, and cash ledger status.

- [ ] **Step 5: Verify workflow**

Manual test: select patient, add item, select therapist, mark `Lunas`. Expected: cart total, commission lock, and success toast update.

## Task 7: Build Rekam Medis Tablet View

**Files:**
- Create: `prototype/src/views/MedicalRecordView.vue`
- Create: `prototype/src/components/Timeline.vue`
- Create: `prototype/src/components/PhotoCompare.vue`

- [ ] **Step 1: Build patient summary and timeline**

Show patient identity, medical record ID, phone number, recent treatment history, and treatment notes.

- [ ] **Step 2: Build photo lane**

Show before/after placeholders as cloud-object references, not stored database blobs.

- [ ] **Step 3: Add save state**

Saving a note shows a short progress state and then a saved confirmation.

- [ ] **Step 4: Verify tablet layout**

Use browser viewport `1024x768`. Expected: timeline and note entry remain visible without overlap.

## Task 8: Build Gudang Inventory View

**Files:**
- Create: `prototype/src/views/InventoryView.vue`
- Create: `prototype/src/components/ActionDrawer.vue`
- Create: `prototype/src/components/DataTable.vue`

- [ ] **Step 1: Build stock table**

Columns: product, category, total stock, earliest batch, expiry, HPP, status.

- [ ] **Step 2: Build FIFO visualization**

Show ordered batches for selected product with first-out marker.

- [ ] **Step 3: Add purchase drawer**

Drawer fields: supplier, product, batch code, quantity, HPP, expiry date.

- [ ] **Step 4: Verify FIFO meaning**

Manual test: open a product. Expected: first-out batch is visually obvious and tied to HPP.

## Task 9: Build Manajer Reports View

**Files:**
- Create: `prototype/src/views/ReportsView.vue`
- Create: `prototype/src/components/ReportPreview.vue`

- [ ] **Step 1: Build report switcher**

Tabs: Keuangan PDF, Stok Excel, Komisi Terapis Excel.

- [ ] **Step 2: Build previews**

PDF preview includes clinic letterhead, report title, transaction table, balance, and signature area. Excel previews show stock and commission columns.

- [ ] **Step 3: Add export state**

Export button shows progress and then success toast. Label the result as prototype-only.

- [ ] **Step 4: Verify accounting safety**

Search UI copy. Expected: no copy claims real payroll, real accounting, or real export files are generated.

## Task 10: Responsive And Motion QA

**Files:**
- Create: `prototype/tests/smoke.spec.ts`
- Modify: prototype UI files as defects are found.

- [ ] **Step 1: Add Playwright smoke checks**

Checks:
- Login appears.
- Role login reaches app shell.
- Navigation reaches all four modules.
- POS payment flow reaches success state.
- Viewports `1440x900`, `1024x768`, and `390x844` render without horizontal scroll.

- [ ] **Step 2: Run smoke checks**

Run:

```powershell
npm run test:smoke
```

Expected: all smoke checks pass.

- [ ] **Step 3: Browser visual check**

Open the dev server in Browser. Capture desktop and mobile screenshots. Expected: no overlap, no over-explained copy, no nested card soup, readable totals, and clear CTAs.

## Task 11: Documentation And Handoff

**Files:**
- Create: `prototype/README.md`
- Modify: `CONTEXT.md` only if prototype scope changes product context.
- Modify: `ARCHITECTURE.md` only if prototype technology differs from this plan.

- [ ] **Step 1: Write run notes**

Document:
- `npm install`
- `npm run dev`
- `npm run build`
- `npm run test:smoke`
- Prototype limitations.

- [ ] **Step 2: Final verification**

Run:

```powershell
npm run build
npm run test:smoke
```

Expected: both commands exit 0.

- [ ] **Step 3: Final report**

Report files changed, commands run, screenshot paths, known limitations, and whether `HALLUCINATION.md` was created or updated.

## Plan Coverage Checklist

- Login and role access: Task 5.
- Kasir POS and `Lunas` commission snapshot: Task 6.
- Rekam Medis tablet and before/after references: Task 7.
- Gudang FIFO and HPP: Task 8.
- Manager PDF/Excel report previews: Task 9.
- Clean structured design system: Tasks 2 and 4.
- Premium useful motion: Tasks 2, 5, 6, 7, 8, 9, and 10.
- Responsive/browser verification: Task 10.
- Prototype limitations: Tasks 9 and 11.
