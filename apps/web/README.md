# SIM-KK Real Services Prototype

Vue + local backend implementation for Sistem Informasi Manajemen Klinik Kecantikan.

## Run

```powershell
npm install
npm run dev
```

Open `http://127.0.0.1:5173`.

The dev command starts:

- API: `http://127.0.0.1:5174`
- Web: `http://127.0.0.1:5173`

Seed login:

- Username: `kasir`
- Password: `simkk-2026`
- Role: `Kasir`

## Verify

```powershell
npm run test:api
npm run build
npm run test:smoke
```

## Scope

- Login verifies stored users and returns a bearer token.
- Kasir POS writes paid transactions, commission snapshots, cash ledger rows, and FIFO stock mutation to a local SQLite file.
- Rekam Medis saves treatment notes and local clinical photo object references.
- Gudang saves supplier purchase batches and recalculates FIFO stock.
- Laporan exports real PDF/XLSX files from stored data.
- Local SQLite data lives at `data/simkk.sqlite` and is regenerated from seed data when absent.
- Local clinical object files live under `storage/clinical`.

## Remaining Production Gaps

- Laravel backend is not installed in this workspace yet.
- PostgreSQL/MySQL production DB is not configured yet.
- External S3-compatible storage credentials are not configured yet.
- WhatsApp integration is not implemented yet.
- Payroll approval/audit policy still needs production rules.
