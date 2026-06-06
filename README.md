# SIM-KK — Sistem Informasi Manajemen Klinik Kecantikan

**Tanggal**: 2026-06-06
**Status saat ini**: v0.1 — Client Preview Package (menunggu approval klien)
**Lokasi source spec**: `C:\Users\stefa\Downloads\Rancangan Sistem Informasi Klinik Kecantikan.pdf`

---

## Client Preview

Paket preview visual untuk dikirim ke klien tersedia di:

- **[`outputs/DELIVERABLE.md`](outputs/DELIVERABLE.md)** — dokumen utama untuk klien (elevator pitch, daftar 7 layar, arsitektur, 4 pertanyaan approval, timeline).
- **[`outputs/changelog.md`](outputs/changelog.md)** — riwayat versi (v0.1 → v0.5 → v1.0).
- **[`outputs/sim-kk-ui-previews/CLIENT-NOTE.md`](outputs/sim-kk-ui-previews/CLIENT-NOTE.md)** — cover note teknis untuk developer review.
- **[`outputs/sim-kk-ui-previews/`](outputs/sim-kk-ui-previews/)** — folder berisi 7 layar HTML statis (Login, POS, Rekam Medis, Gudang, Laporan hub, Laporan Arus Kas, Laporan Stok & Komisi Terapis). Buka `login.html` di browser untuk mulai.

---

## Project Knowledge Base

Dokumen utama project (lihat juga `AGENTS.md` untuk code conventions dan project structure):

| Dokumen | Isi |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Code map, conventions, anti-patterns, commands |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | System architecture (verified vs target stack) |
| [`CONTEXT.md`](CONTEXT.md) | Product spec (target users, business rules, constraints) |
| [`HALLUCINATION.md`](HALLUCINATION.md) | Anti-fabrication log: apa yang di-infer vs diverifikasi |
| [`docs/EXTERNAL-DEPENDENCIES.md`](docs/EXTERNAL-DEPENDENCIES.md) | PRD → 3rd-party API map (Telegram, S3, hosting) |
| [`docs/superpowers/plans/`](docs/superpowers/plans/) | Implementation plans (D1-readiness, premium prototype) |
| [`docs/superpowers/specs/`](docs/superpowers/specs/) | Design specs (premium prototype, real services) |

## Project status

Laravel + Vue apps under `apps/api` and `apps/web`. Dev uses SQLite; production target is Cloudflare D1. Telegram (not WhatsApp) chosen for patient notifications. See `ARCHITECTURE.md` and `HALLUCINATION.md` for verified-vs-claimed boundaries.
