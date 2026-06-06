# SIM-KK Editorial Luxury — Static HTML Previews

Static HTML preview untuk **kirim ke client** sebelum implement Vue. Preview ini cuma visual mockup — tidak ada Vue, Vite, atau API wiring.

Lihat juga: **[CLIENT-NOTE.md](CLIENT-NOTE.md)** untuk cover letter + checklist approval.

## Cara buka

**Cara 1 — Double-click di File Explorer**
Buka `login.html` di browser (Chrome/Edge/Firefox).

**Cara 2 — Static server (recommended)**
```bash
cd outputs/sim-kk-ui-previews
npx http-server -p 8080
# buka http://127.0.0.1:8080
```

**Cara 3 — Python**
```bash
cd outputs/sim-kk-ui-previews
python -m http.server 8080
```

## Screenshots

Lihat folder `assets/` untuk screenshot otomatis dari Playwright headless. File `*.png` per layar.

## Design rationale

**Editorial Luxury** — replacement untuk "decent functional prototype" existing. Filosofi:

1. **Tipografi sebagai arsitektur** — display serif (Fraunces, substitute dari GT Sectra) untuk hero + section H1, grotesk (Inter, substitute dari Söhne) untuk body. Mono (JetBrains) untuk data numerik.
2. **Ruang putih sebagai kemewahan** — section padding 96-160px (`py-canvas`), card padding 32-48px (`p-card`).
3. **Fotografi sebagai bukti** — service tile pakai foto 16:9, treatment before/after 3:4 portrait, foto terapis sebagai avatar picker.
4. **Warna editorial** — cream `#F5F1EA` base, deep forest `#1F3D36` primary, champagne `#C4A572` accent (commission lock), rose `#A85A4A` danger, leaf `#6B8E5A` success.
5. **Motion mahal** — 480-720ms ease `cubic-bezier(0.2, 0.8, 0.2, 1)`, lift on hover, no bounce/elastic.

## File index

| File | Layar | Fungsi | PRD |
|---|---|---|---|
| `login.html` | Login full-bleed | Editorial split layout. Role chip click interaktif. | — |
| `pos.html` | POS bento | 6 service tiles + cart panel + avatar therapist picker + metode pill row. | 3.2 |
| `rekam-medis.html` | Rekam Medis kanvas | Tabs (Catatan/Foto/History) + autosave indicator + portrait photo + serif italic timeline. | 3.2 |
| `gudang.html` | Gudang table | Filter chip + striped table + status chips + slide-in drawer. | 2.2.4 |
| `laporan.html` | Laporan hub | 4 report card (Arus Kas, Stok&Komisi, Daily Report, Inventory Movements). | — |
| `laporan-arus-kas.html` | Laporan Arus Kas PDF | KOP, ID Transaksi, Debit, Kredit, Saldo, dual TTD pojok kanan bawah. Utilitarian B&W. | 3.3.1 |
| `laporan-stok-komisi.html` | Laporan Stok & Komisi Terapis XLSX | 6 kolom (ID Pegawai, Nama, Jumlah Tindakan, Komisi, Gaji Pokok, Take-Home Pay). Utilitarian B&W. | 3.3.2 |
| `laporan-daily.html` | Daily Report PDF (Naavagreen) | Extension PRD 3.3.1 — 8 section detail operasional + dual TTD. | 3.3.1+ |
| `laporan-inventory-movements.html` | Inventory Movements XLSX | 11 kolom mutasi barang per hari. Tidak di PRD — ekstensi user request. | ext |

## Security note

Semua Tailwind dimuat via pinned version `3.4.16` dari `cdn.tailwindcss.com` dengan **SRI integrity hash** + `crossorigin="anonymous"` untuk mitigasi CDN compromise. Hash terverifikasi via `openssl dgst -sha384`.

Font dimuat dari Google Fonts CDN (preconnect enabled untuk performance). Tidak ada inline script berbahaya.

## Apa yang harus di-review

Sebelum saya lanjut ke Vue implementation (Task 2-8 dari plan):

- [ ] Apakah **aesthetic** sesuai? (cream + forest + serif italic hero)
- [ ] Apakah **information hierarchy** terasa premium? (display H1 besar, body kecil)
- [ ] Apakah **POS** masih usable? (catalog kiri, cart kanan, metode pill di payment)
- [ ] Apakah **Daily Report** sesuai dengan image referensi? (8 section + dual TTD)
- [ ] Apakah **Inventory Movements** sesuai image? (11 kolom per barang per hari)

Kalau approved, saya implement ke Vue 3 + Tailwind via `tailwind.config.ts`. Effort ~22-28 jam (7 phase commits).
