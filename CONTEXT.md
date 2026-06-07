# Project Context

Source evidence: `C:\Users\stefa\Downloads\Rancangan Sistem Informasi Klinik Kecantikan.pdf`

Evidence status: this workspace contains the source DPPL PDF, context/architecture docs, and a local full-stack implementation slice under `prototype/`. Items marked "Inferred" are derived from the PDF wording, not from production deployment evidence.

## Project Summary

SIM-KK is a planned Sistem Informasi Manajemen Klinik Kecantikan for a beauty clinic client in Samarinda.

The source DPPL describes a system that combines:

- Rekam medis pasien.
- Inventaris/gudang.
- Point of Sale and kasir flow.
- Komisi terapis.
- Tata kelola keuangan and operational reports.

The project is currently a design/specification workspace plus a local Vue/Node implementation. Backend API, local SQLite persistence, bearer-token login, local clinical object storage, POS finalization, FIFO mutation, and PDF/XLSX exports are implemented locally. Laravel, PostgreSQL/MySQL, external S3, WhatsApp, deployment, and production audit policy are not implemented.

## Vision And Purpose

Verified from source:

- Convert clinic operational needs into a technical software design.
- Separate interface logic from database and business logic.
- Provide a prototype-level picture of clinic operations, especially Kasir, Gudang, and Rekam Medis.
- Provide a normalized database design direction.
- Reduce future operational risk through design before implementation.

Inferred:

- The product should reduce manual clinic administration by connecting clinical history, sales, stock, payroll/commission, and financial reporting in one system.
- The system should prioritize fast daily operations, especially at the cashier desk and treatment room.

## Target Users

Verified roles from source:

- Kasir: handles POS transactions, payment status, receipts, and cash ledger output.
- Terapis: uses patient medical records and treatment history, including before/after photo uploads.
- Admin Gudang: manages inventory, supplier purchases, stock movement, and FIFO valuation.
- Manajer: consumes reports such as cash flow, profit/loss, stock, and therapist commission.

Inferred:

- Pasien is primarily represented as a data subject in the system, not necessarily as a login user.

## Core Features

- Role-based login for Kasir, Terapis, Gudang, and Manajer.
- Patient records with complaints, treatments, chronological treatment history, and before/after clinical photos.
- POS transaction entry for services and products.
- Therapist commission snapshot when transaction status becomes `Lunas`.
- Receipt/faktur printing and cash ledger recording.
- Inventory purchase entry from suppliers.
- FIFO stock valuation to prioritize products with nearer expiry or earlier stock batches.
- PDF financial reports.
- Excel stock and therapist commission reports.

## User Workflows

### Login And Role Access

- User goal: enter the system according to job responsibility.
- Entry point: login screen.
- Main path: user submits username and password.
- Success state: system grants access according to role.
- Important rules: password is stored hashed/encrypted; roles listed in the PDF are Kasir, Terapis, Gudang/Admin, and Manajer.

### Kasir POS Transaction

- User goal: record service/product purchases and finish payment quickly.
- Entry point: cashier dashboard / Point of Sale.
- Main path: kasir selects services or products, adds them to billing cart, selects assigned therapist, then marks transaction as paid.
- Success state: transaction becomes `Lunas`, receipt is printed, cash ledger receives incoming cash entry.
- Important rules: therapist commission is calculated and stored as a permanent transaction snapshot at payment finalization.

### Rekam Medis Treatment History

- User goal: record and view patient clinical treatment history.
- Entry point: tablet-friendly medical record interface.
- Main path: terapis opens patient record, enters complaints/actions, uploads before/after photos, and reviews chronological history.
- Success state: treatment record and photo timeline are saved.
- Important rules: source recommends cloud object storage for clinical photos so the main server and database do not become heavy.

### Gudang Inventory

- User goal: enter supplier stock purchases and preserve correct stock valuation.
- Entry point: inventory/admin gudang module.
- Main path: gudang enters purchase invoice, product stock, and cost/HPP.
- Success state: stock and cost records are available for FIFO-based mutation.
- Important rules: FIFO is required so earlier or nearer-expiry skincare stock is sold/used first.

### Manager Reporting

- User goal: review financial, stock, and therapist commission output.
- Entry point: reporting module.
- Main path: manager exports financial PDF or stock/commission Excel.
- Success state: report file is generated.
- Important rules: commission/payroll Excel values must come from approved database transaction history and should not be manually manipulated in the app.

## Business Rules

- Login must distinguish user access by role.
- Patient data includes name, age, address, phone number, and unique medical record ID.
- Patient phone number is intended for WhatsApp integration, but implementation details are unknown.
- Transaction detail stores product/service, transaction, therapist, and commission value.
- Commission value is stored permanently as a snapshot after transaction approval/payment.
- Receipt/faktur generation also records cash inflow.
- FIFO stock valuation is a core inventory rule.
- Financial reports use PDF format and include clinic letterhead, report title, structured transaction table, balance, and signature area.
- Stock and therapist commission reports use `.xlsx` format.

## Project Constraints

- Source recommends Laravel for backend/business logic.
- Source recommends Vue.js for interactive POS frontend.
- Source allows PostgreSQL or MySQL; final database engine is not chosen in the workspace.
- Source recommends third-party S3-compatible object storage for before/after clinical photos.
- Clinical photo storage should avoid bloating the primary server and database.
- UI should support fast POS work and tablet-friendly use in treatment rooms.
- Current workspace has a Vue/Vite frontend and Node/Express backend under `prototype/`.
- Local SQL.js persistence exists; production Laravel migrations and DB runtime config do not exist yet.

## Style And Conventions

- Operational UI should be direct, dense, and fast, especially POS.
- POS layout: left side service/product tiles, right side billing cart.
- POS must include direct therapist selection to lock commission ownership.
- Medical record UI should be touchscreen/tablet friendly.
- Before/after upload should support drag-and-drop or direct capture when implemented.
- Logo philosophy is marked in the PDF as "Customize by user"; no fixed brand system is defined yet.

## Operational Notes

- Treat the PDF as the product design baseline.
- Treat `prototype/` as a local full-stack slice, not proof of final Laravel/cloud production readiness.
- Future Laravel or cloud implementation should create code-level evidence before docs claim production server-side features are live.
- Keep financial and commission calculations auditable because reports and payroll depend on approved transaction history.
- Do not store clinical photos directly in relational database rows unless a later technical decision explicitly changes the storage model.

## Unknowns

- Local implementation stack is Vue 3, TypeScript, Vite, CSS tokens, Lucide icons, Express, SQL.js, PDFKit, ExcelJS, Node API tests, and Playwright smoke tests.
- No final database engine is selected between PostgreSQL and MySQL.
- No deployment target, hosting provider, or environment variables are defined.
- No API endpoints, authentication package, or authorization middleware are implemented.
- No exact commission formula is specified beyond storing `Nilai_komisi`.
- No detailed inventory schema for batches, expiry dates, suppliers, or stock mutation ledger is provided.
- No privacy, consent, audit-log, or retention policy for clinical photos is provided.
