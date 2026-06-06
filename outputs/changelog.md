# SIM-KK — Changelog

Riwayat versi project SIM-KK. Versi mengikuti siklus hidup: dari mockup statis (v0.x) ke aplikasi berjalan (v0.5) ke production live (v1.0).

---

## v0.1 — Client Preview Package (2026-06-06)

Status: **dikirim ke klien, menunggu approval**.

Yang ada di paket ini:

- **7 layar HTML statis** (Login, POS, Rekam Medis, Gudang, Laporan hub, Laporan Arus Kas PDF, Laporan Stok & Komisi Terapis XLSX) dengan aesthetic Editorial Luxury.
- **Design system specification** lengkap — tipografi (Fraunces + Inter + JetBrains Mono), palet warna (cream, deep forest, champagne, rose, leaf), motion guidelines (480-720ms ease).
- **Schema database siap** untuk 11 tabel (users, patients, products, transactions, transaction_items, treatment_notes, clinical_photos, inventory_batches, supplier_purchases, cash_ledger, dan tabel laporan baru: daily_cash_float, daily_closing, stok_mutasi).
- **Cover note klien** (`outputs/sim-kk-ui-previews/CLIENT-NOTE.md`) dengan checklist approval 8 poin.
- **Dokumen deliverable utama** (`outputs/DELIVERABLE.md`) untuk ditandatangani klien.

Yang belum ada di v0.1:
- Aplikasi berjalan (no Vue, no Vite, no API wiring).
- Database live (hanya schema).
- Integrasi Telegram.
- Production deploy.

---

## v0.5 — Vue 3 Frontend Implementation (rencana)

Status: **siap dimulai setelah klien menyetujui v0.1**.

Yang akan dibangun:

- **Vue 3 + Vite + Tailwind** untuk 7 view (Login, POS, Rekam Medis, Gudang, Laporan hub + 3 sub-laporan).
- **Pinia store** untuk state management auth, cart POS, dan inventory.
- **Vue Router** dengan role-based guards (Kasir, Terapis, Gudang, Manajer).
- **API integration** ke Laravel backend yang sudah live (sqlite di dev, D1 di staging).
- **Form validation** end-to-end dengan error handling yang rapi.
- **Responsive design** — POS di desktop, Rekam Medis di tablet, Gudang di keduanya.
- **Aksesibilitas dasar** — keyboard navigation, ARIA labels, kontras warna sesuai WCAG AA.
- **Smoke test** Playwright untuk happy path 4 role.

Target selesai: **+7-8 hari kerja** dari kickoff.

---

## v1.0 — Production (rencana)

Status: **menunggu v0.5 selesai dan QA pass**.

Yang akan di-deploy:

- **Cloudflare D1 production** dengan data real (bukan sample).
- **Cloudflare R2 bucket** untuk foto klinis, tanda tangan, dan PDF laporan — diamankan dengan signed URL.
- **Telegram webhook aktif** — bot mengirim notifikasi janji temu, reminder aftercare, alert stok menipis ke owner.
- **Laravel VPS** berjalan dengan HTTPS, backup database harian, monitoring uptime.
- **CI/CD pipeline** — PHPUnit (logic), D1 local (schema), Playwright (UI smoke).
- **Production seed** — 4 user default (Kasir, Terapis, Gudang, Manajer) + master data klinik (logo, alamat, KOP, daftar layanan).
- **Training sesi 1 hari** untuk owner dan staf klinik.
- **Dokumentasi user guide** PDF Bahasa Indonesia untuk tiap role.

Target go-live: **+7-8 hari kerja** setelah v0.5 acceptance.

---

## Versi Mendatang (post v1.0)

Setelah v1.0 stabil, kandidat fitur berikutnya (tapi belum dikunci):

- Appointment booking online (pasien pesan via WhatsApp).
- Loyalty program (poin treatment ke-10 gratis).
- Multi-cabang (untuk klinik dengan >1 lokasi).
- Integrasi payment gateway (Midtrans/Xendit untuk QRIS otomatis).
- Dashboard analitik (revenue, retention, produk terlaris) di mobile app.
- Auto-backup bulanan ke Google Drive.

---

**Format**: [MAJOR.MINOR] — [Label Tanggal] — Status.
**Tanggal update terakhir**: 2026-06-06.
