# SIM-KK Real Services Design

## Goal

Replace the prototype-only simulation layer with real local services: persistent data, authenticated API calls, transaction finalization, FIFO stock movement, clinical note/photo persistence, and downloadable report files.

## Scope For This Slice

- Keep the current Vue app and visual design.
- Add a Node/Express backend because this workstation has Node/npm but no PHP, Composer, MySQL, or PostgreSQL CLI available.
- Use SQL.js SQLite file persistence under `prototype/data/simkk.sqlite`.
- Generate real PDF and XLSX files from stored data.
- Store clinical photo payloads in local object-storage-compatible folders under `prototype/storage/clinical`.
- Keep Laravel plus PostgreSQL/MySQL as the target production lane documented in `ARCHITECTURE.md`.

## Real Behaviors

- Login verifies a role/user against stored user records and returns a bearer token.
- Bootstrap reads users, patients, services, therapists, transactions, inventory, and reports from the database.
- POS finalization creates a paid transaction, stores commission as an immutable snapshot, records cash ledger income, and consumes product stock through FIFO batches.
- Rekam medis note save inserts a chronological treatment record.
- Clinical photo upload writes a file and stores the object reference in the database.
- Inventory purchase inserts a supplier batch, recomputes FIFO order, and updates stock totals.
- Reports export actual files:
  - Financial PDF with clinic header, table, saldo, and signature area.
  - Stock XLSX.
  - Therapist commission XLSX.

## Constraints

- No external credentials are assumed.
- No production S3 provider is configured yet; local storage is a real adapter, not cloud storage.
- No Laravel runtime is installed locally; this slice is a production-shaped bridge inside the existing prototype workspace.

## Verification

- Node API tests prove auth, POS payment, FIFO mutation, medical note persistence, inventory purchase, and report export.
- Vue build proves frontend compile health.
- Playwright smoke tests prove login, module navigation, payment, and download flow in browser.
