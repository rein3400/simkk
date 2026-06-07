# SIM-KK Real Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace prototype simulations with real local services and persistent workflows.

**Architecture:** Vue remains the frontend. A Node/Express backend owns auth, persistence, business rules, local object storage, and report export. SQL.js stores a SQLite database file so the app has real persistence without requiring native DB tooling on this machine.

**Tech Stack:** Vue 3, Vite, TypeScript, Express, SQL.js, PDFKit, ExcelJS, Node test runner, Playwright.

---

### Task 1: Backend Contract Tests

**Files:**
- Create: `prototype/backend/tests/api.test.mjs`

- [ ] Write tests for login, bootstrap, paid transaction, FIFO stock decrement, medical note save, inventory purchase, and report export.
- [ ] Run `npm run test:api` and confirm it fails because backend modules do not exist yet.

### Task 2: Persistent Backend

**Files:**
- Create: `prototype/backend/server.mjs`
- Create: `prototype/backend/app.mjs`
- Create: `prototype/backend/database.mjs`
- Create: `prototype/backend/seed.mjs`
- Create: `prototype/backend/reporting.mjs`
- Create: `prototype/backend/storage.mjs`

- [ ] Implement SQL schema, seed data, DB read/write helpers, and Express app factory.
- [ ] Implement auth, bootstrap, POS payment, medical notes/photos, inventory purchase, and report export endpoints.
- [ ] Run `npm run test:api` and confirm green.

### Task 3: Frontend API Wiring

**Files:**
- Create: `prototype/src/services/api.ts`
- Modify: `prototype/src/App.vue`
- Modify: `prototype/src/views/LoginView.vue`
- Modify: `prototype/src/views/PosView.vue`
- Modify: `prototype/src/views/MedicalRecordView.vue`
- Modify: `prototype/src/views/InventoryView.vue`
- Modify: `prototype/src/views/ReportsView.vue`
- Modify: `prototype/src/types/domain.ts`

- [ ] Replace fixture imports in views with props and API calls.
- [ ] Login through backend.
- [ ] Refresh app data after writes.
- [ ] Change simulation copy to real persistence/export copy.

### Task 4: Dev/Test Runtime

**Files:**
- Modify: `prototype/package.json`
- Modify: `prototype/vite.config.ts`
- Modify: `prototype/playwright.config.ts`
- Modify: `prototype/tests/smoke.spec.ts`
- Modify: `prototype/README.md`
- Modify: `ARCHITECTURE.md`
- Modify: `HALLUCINATION.md`

- [ ] Add backend dependencies/scripts.
- [ ] Proxy `/api` from Vite to backend.
- [ ] Update smoke tests for real login/payment/export.
- [ ] Document local real-services scope and remaining production gaps.
- [ ] Run `npm run build`, `npm run test:api`, and `npm run test:smoke`.
