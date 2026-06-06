# SIM-KK — Cover Note untuk Client

**Tanggal**: 2026-06-06
**Project**: Sistem Informasi Manajemen Klinik Kecantikan (SIM-KK)
**Dipersembahkan untuk**: Klien Pengusaha Klinik Kecantikan, Samarinda
**Status**: Visual Preview v0.1 — siap untuk review klien

> **Dokumen utama untuk klien**: Lihat **[`../DELIVERABLE.md`](../DELIVERABLE.md)** untuk paket lengkap (elevator pitch, deskripsi 7 layar, arsitektur, 4 pertanyaan approval, timeline).
> Dokumen ini (`CLIENT-NOTE.md`) adalah catatan teknis internal — checklist approval, design rationale, dan TODO developer.

---

## Apa yang dikirim

Paket ini adalah **visual mockup statis** dari 7 layar utama aplikasi SIM-KK. Bukan aplikasi berjalan — ini reference visual untuk disetujui klien sebelum tim development mulai implementasi penuh di Vue 3 + Laravel.

## Cara membuka

### Cara 1 (paling mudah) — langsung klik file
Buka `login.html` di browser (Chrome/Edge/Firefox). Klik link di dalam untuk navigasi antar layar.

### Cara 2 (recommended) — static server
```bash
cd outputs/sim-kk-ui-previews
npx http-server -p 8080
```
Buka `http://127.0.0.1:8080/login.html`

### Cara 3 — Python built-in
```bash
cd outputs/sim-kk-ui-previews
python -m http.server 8080
```

## Daftar layar

| # | Layar | File | Sumber spesifikasi |
|---|---|---|---|
| 1 | Login | `login.html` | Editorial split layout |
| 2 | Point of Sale (POS) | `pos.html` | PRD 3.2 |
| 3 | Rekam Medis | `rekam-medis.html` | PRD 3.2 (ramah tablet) |
| 4 | Gudang | `gudang.html` | PRD 2.2.4 (FIFO) |
| 5 | Laporan (hub) | `laporan.html` | 4 report card |
| 6 | Laporan Arus Kas | `laporan-arus-kas.html` | **PRD 3.3.1** (eksplisit: KOP klinik, ID Transaksi, Debit, Kredit, Saldo, dual TTD Manajer + Kasir di pojok kanan bawah) |
| 7 | Laporan Stok & Komisi Terapis | `laporan-stok-komisi.html` | **PRD 3.3.2** (ID Pegawai, Nama Terapis, Jumlah Tindakan, Total Nominal Komisi, Gaji Pokok, Grand Total Take-Home Pay) |
| 8 | Daily Report (Naavagreen reference) | `laporan-daily.html` | Ekstensi PRD 3.3.1 — 8 section operasional detail |
| 9 | Inventory Movements | `laporan-inventory-movements.html` | Reference image 2 — 11 kolom mutasi stok per hari |

Screenshots tersedia di folder `assets/` untuk preview cepat tanpa buka browser.

## Direction estetik: Editorial Luxury

- **Tipografi**: Display serif (Fraunces) untuk hero/title, grotesk (Inter) untuk body, monospace (JetBrains Mono) untuk data numerik & laporan.
- **Palette**: Cream `#F5F1EA` base, deep forest `#1F3D36` primary, champagne `#C4A572` accent (commission lock), rose `#A85A4A` danger, leaf `#6B8E5A` success.
- **Layout**: Whitespace generous (96-160px section padding), 32-48px card padding. Density hanya di area data (tabel, cart).
- **Motion**: 480-720ms ease `cubic-bezier(0.2, 0.8, 0.2, 1)`, hover lift, no bounce/elastic.
- **Fotografi**: Unsplash untuk mockup. Production akan pakai stock photo sesuai brief klien.

## Role-based access (4 role sesuai PRD 2.2.1 + 1 admin)

| Role | Default View | Akses |
|---|---|---|
| **Kasir** | POS | POS, transaksi, receipt, komisi, WhatsApp reminder |
| **Terapis** | Rekam Medis | Rekam medis, treatment notes, foto before/after, WhatsApp aftercare |
| **Gudang** | Inventory | Stok, batch FIFO, HPP, pembelian supplier |
| **Manajer** | Laporan | SEMUA + laporan export + approval Daily Report + audit |
| **Admin** | Laporan | (ekivalen Manajer, sesuai PRD 2.3.1) |

## Yang sudah sesuai PRD eksplisit

- ✅ **3.3.1** Laporan Keuangan PDF: KOP, judul, ID Transaksi / Debit / Kredit / Saldo, dual TTD Manajer + Kasir di pojok kanan bawah
- ✅ **3.3.2** Laporan Stok & Komisi Terapis XLSX: 6 kolom + note "tidak dapat dimanipulasi"
- ✅ **2.2.1** 4 role dengan akses berbeda (Kasir, Terapis, Gudang, Manajer)
- ✅ **2.2.2** Rekam Medis: CRUD dengan foto upload ke object storage
- ✅ **2.2.3** Transaksi & Kasir: snapshot komisi saat Lunas, cetak faktur
- ✅ **2.2.4** Inventaris: input barang masuk dengan HPP untuk FIFO

## Yang masih TODO sebelum production

- [ ] **DB schema untuk Daily Report** (3 tabel baru: `daily_cash_float`, `daily_closing`, `stok_mutasi` + `users.signature_path`)
- [ ] **D1 + R2 deploy** (Cloudflare D1 production + R2 bucket untuk foto klinis)
- [ ] **Vue rewrite** untuk 5 view existing + 2 view baru
- [ ] **Real-time FIFO mutation** via `TransaksiService`
- [ ] **Daily Report workflow** (draft → submitted → approved → final)
- [ ] **Tanda tangan image upload** untuk Manajer & Kasir via `StorageService`
- [ ] **CI pipeline** (PHPUnit sqlite + d1-local + Playwright smoke)
- [ ] **Production seed** (`ProductionBootstrapSeeder` — 4 user default + sample master data)

## Yang butuh approval klien

1. **Aesthetic direction** — Editorial Luxury cream+forest+serif. Atau ada referensi visual lain?
2. **Logo** — PRD 3.1 filosofi "Customize by user". Logo final?
3. **Font** — Fraunces+Inter+JetBrains Mono adalah free alternative. Klien mau font berbayar (GT Sectra/Söhne) atau free version cukup?
4. **Data sample** — Nama terapis (Rani, Dewi, Sari, Maya) & produk skincare di mockup. Real client data atau ganti?
5. **Nama klinik** — Sample pakai "KLINIK KECANTIKAN SIM-KK" + "Jl. Klinik No. 1, Samarinda". Real info?
6. **Daily Report sections** — 8 section sesuai image Naavagreen adalah extension dari PRD 3.3.1 yang minimal. Klien konfirmasi mau 8 section atau cukup minimal?
7. **Inventory Movements** — Ekstensi user request, tidak di PRD. Klien konfirmasi mau include?
8. **Telegram bot token** — Untuk notifikasi pasien, perlu bot via @BotFather. Token di mana?

## Timeline estimate

- DB migration + D1 deploy: 1.5-2 hari
- Vue UI rewrite (7 view): 3-4 hari
- Daily Report workflow + signature: 1 hari
- Polish + a11y + CI: 1 hari
- **Total**: ~7-8 hari kerja

---

**Hubungi**: [nama contact] untuk pertanyaan atau approval.
**Next step**: Setelah approval aesthetic + content, mulai implement Vue 3 + Laravel untuk production-ready.
