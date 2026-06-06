# SIM-KK Editorial Luxury UI Design

## Goal

Replace UI `apps/web` dari "decent functional prototype" jadi **Editorial Luxury** yang membuat klinik terasa premium — tanpa kehilangan operational density yang kasir/terapis butuhkan. Plus: tambah 2 view laporan baru (Daily Report + Inventory Movements) per referensi user 2026-06-06.

## Source

- PDF PRD: `C:\Users\stefa\Downloads\Rancangan Sistem Informasi Klinik Kecantikan.pdf`
- User reference image 1: Daily Report (PT Naavagreen Indonesia) — akhir shift kasir
- User reference image 2: Inventory Movements (Naavagreen) — mutasi stok harian

## 5 Prinsip Desain

1. **Editorial hierarchy**: display serif 80-160px untuk nama layar + hero numbers, body grotesk 15-17px untuk density. Tipografi jadi arsitektur.
2. **Ruang putih sebagai kemewahan**: 96-160px section padding, 32-48px card padding, density hanya di area data (tabel, cart).
3. **Fotografi sebagai bukti**: foto before/after 16:9 portrait dengan frame tipis, foto treatment sebagai kategori cue, foto terapis di header.
4. **Warna editorial**: cream `#F5F1EA` base, deep forest `#1F3D36` primary, champagne `#C4A572` accent, charcoal `#0F0F0F` ink. Sage-only current → diperluas jadi editorial palette.
5. **Motion yang terasa mahal**: 480-720ms reveals, ease `cubic-bezier(0.2, 0.8, 0.2, 1)`, parallax halus di hero, no bounce/elastic. Reduce-motion aware.

## Out of Scope (YAGNI)

- Dark mode (cuma 1 tema — light editorial)
- 3D illustrations
- Animation library berat (GSAP) — pakai CSS transitions + Vue `<Transition>`
- Design token JSON terpisah (tailwind.config = single source)
- Storybook (overkill untuk 5 view → 7 view dengan Daily Report + Inventory Movements)

## Stack

- **Rendering**: Vue 3 + Vite + TypeScript (existing)
- **Styling**: Tailwind CSS utility-first (replace `tokens.css` 1276 baris custom CSS dengan Tailwind tokens via `tailwind.config.ts`)
- **Typography**: Fraunces (serif display, GT Sectra substitute) + Inter (grotesk body, Söhne substitute) + JetBrains Mono
- **Photo**: Unsplash CDN (free license) untuk mockup
- **State**: Existing Vue refs + Pinia kalau kompleks (YAGNI kalau masih 1 file per view)
- **Animation**: CSS transitions + Vue `<Transition>` saja, no GSAP

## Design Tokens (tailwind.config.ts)

```ts
// tailwind.config.ts
export default {
  content: ["./index.html", "./src/**/*.{vue,ts}"],
  theme: {
    extend: {
      colors: {
        cream:       "#F5F1EA",   // base canvas
        parchment:   "#EBE5D8",   // surface elevated
        stone:       "#DCD5C7",   // border subtle
        ink:         "#0F0F0F",   // primary text
        graphite:    "#3A3A38",   // body
        sage:        "#5C6F66",   // muted
        forest:      "#1F3D36",   // primary brand
        forest_deep: "#13261F",   // hover
        champagne:   "#C4A572",   // accent / commission lock
        champagne_d: "#9C8252",   // accent dark
        rose:        "#A85A4A",   // danger / error
        leaf:        "#6B8E5A",   // success / paid
      },
      fontFamily: {
        display: ['"Fraunces"', '"GT Sectra"', '"Tiempos Headline"', '"Playfair Display"', 'serif'],
        body:    ['"Inter"', '"Söhne"', 'system-ui', 'sans-serif'],
        mono:    ['"JetBrains Mono"', '"IBM Plex Mono"', 'monospace'],
      },
      fontSize: {
        'display-2xl': ['10rem',  { lineHeight: '0.85', letterSpacing: '-0.03em' }],
        'display-xl':  ['7.5rem', { lineHeight: '0.86', letterSpacing: '-0.025em' }],
        'display-lg':  ['5rem',   { lineHeight: '0.92', letterSpacing: '-0.02em' }],
        'display-md':  ['3.5rem', { lineHeight: '0.98', letterSpacing: '-0.015em' }],
        'display-sm':  ['2.25rem',{ lineHeight: '1.05', letterSpacing: '-0.01em' }],
        'body-lg':     ['1.125rem',{ lineHeight: '1.55', letterSpacing: '0' }],
        'body':        ['0.9375rem',{lineHeight: '1.5',  letterSpacing: '0' }],
        'body-sm':     ['0.8125rem',{lineHeight: '1.4',  letterSpacing: '0' }],
        'caption':     ['0.6875rem',{lineHeight: '1.3',  letterSpacing: '0.06em' }],
      },
      spacing: {
        'canvas': '96px',
        'canvas-lg': '160px',
        'card': '32px',
        'card-lg': '48px',
      },
      transitionTimingFunction: { 'editorial': 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
      transitionDuration: { '480': '480ms', '720': '720ms' },
      boxShadow: {
        'paper':   '0 1px 0 rgba(15,15,15,0.04), 0 18px 48px -24px rgba(15,15,15,0.18)',
        'lift':    '0 32px 80px -28px rgba(15,15,15,0.32)',
        'inset':   'inset 0 0 0 1px rgba(15,15,15,0.06)',
      },
    },
  },
};
```

## Type Scale Applied

| Lokasi | Token | Contoh |
|---|---|---|
| Login hero "SIM-KK" | `display-2xl` | 160px serif italic |
| Login sub | `body-lg` | 18px grotesk |
| Page H1 (Rekam Medis, Gudang, Laporan) | `display-lg` | 80px serif |
| Section H2 | `display-md` | 56px serif |
| Card title | `display-sm` | 36px serif |
| Body, button, input | `body` | 15px grotesk |
| Data table, monospaced numbers | `body` + `tabular-nums` | 15px grotesk |
| Eyebrow, label uppercase | `caption` | 11px tracked |

## Font Loading

```html
<!-- index.html <head> -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;1,9..144,400;1,9..144,500&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
```

## Shell — Top Nav + Spacious Canvas

Replace `app-shell` (sidebar 236px) → top nav horizontal + canvas workspace.

```
┌──────────────────────────────────────────────────────────────────┐
│  SIM-KK · Samarinda          [Search cmd+k]      [Bell] [User]    │  ← 72px top nav, backdrop-blur, hairline border-b
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─Eyebrow─Role──────────────────────┐                          │
│  │                                    │                          │  ← page header
│  │  Display H1 80px serif             │                          │     80-160px top padding
│  │  italic untuk emphasis             │                          │
│  │  Subhead 18px grotesk              │                          │
│  └────────────────────────────────────┘                          │
│                                                                  │
│  ┌─[View switcher pills]──────────────────────────────────────┐  │
│  │  Today · Pending · Stock · Commission                     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌─Canvas: grid 12-col, gap 32px, max-w 1440px, mx-auto────────┐  │
│  │                                                            │  │
│  │  [Bento cards: variative sizes per role]                   │  │
│  │                                                            │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

**Top nav rules**:
- Height 72px, sticky, `backdrop-blur-md bg-cream/80 border-b border-stone`
- Logo left (wordmark "SIM-KK" display-sm serif + caption "Samarinda")
- Search center: cmd+k command palette
- Right: notification bell, user avatar circle dengan nama + role
- No logout button in nav — pakai cmd+k → "Sign out"

**Role differentiation via nav variant**:
- Kasir: lihat Kasir + Laporan ringkas
- Terapis: lihat Rekam Medis + Jadwal
- Gudang: lihat Gudang + Stok
- Manajer: lihat semua (overview) + Laporan

**Mobile** (< 768px): top nav collapse → logo + hamburger. Hamburger buka bottom sheet dengan role-aware view list.

## View-Specific Layout Patterns

### Login (full-bleed editorial)
- Split 50/50 desktop, single-column mobile
- Kiri: H1 "SIM-KK" display-2xl serif italic, di-stagger 1 per huruf via CSS animation, slogan `body-lg`, 3 badge proof
- Kanan: form login center, input underline-only (no boxed inputs), primary button outline-only dengan hover fill
- Background: cream dengan 1 foto full-bleed treatment (Unsplash woman facial) di-overlay 30% cream

### POS (bento dashboard, 12-col grid)
```
[           Hero metrics: today revenue           ][  cart panel  ]
[ 2 col span                ][ 2 col span          ][  3 col span ]
[ service tile  ][ service tile  ][ service tile   ][  sticky      ]
[ service tile  ][ service tile  ][ service tile   ][  cart lines  ]
[ patient + therapist selector inline 8 col        ][  payment     ]
```
- Service tiles: foto 16:9 di atas, nama display-sm, harga mono
- Cart panel sticky right
- "Terapis bertugas" dropdown diubah jadi **avatar picker** (3-4 foto terapis, klik lock komisi)
- **Metode bayar**: pill row di payment summary: Tunai | Transfer BCA | Transfer Mandiri | QRIS BCA | QRIS Mandiri | EDC (collapsed dropdown) — single method per transaksi (sesuai PRD)

### Rekam Medis (kanvas-first)
- Header: foto pasien lingkaran 96px + nama display-lg + meta
- Tab horizontal: Catatan / Foto / Treatment History
- Catatan: textarea underline, autosave indicator "Disimpan 2 detik lalu"
- Foto: dropzone lebar dengan preview 3:4 portrait
- Timeline: kiri tanggal serif italic, kanan treatment block

### Gudang (table-forward)
- Sticky filter bar: search + filter kategori + "Akan kadaluarsa" toggle + "Mutasi" link ke Inventory Movements
- Table: striped minimal, monospaced numbers, status chip (Hijau/Aman, Champagne/Menipis, Rose/Kadaluarsa)
- Right drawer 480px: detail batch + foto supplier + tombol "Tambah Pembelian"
- FIFO visualization: stack vertikal batch dengan oldest-on-top arrow

### Laporan (typographic hub, NEW dengan 2 sub-view)

**Parent view (`laporan.html`)** — 3 report card besar:
- Card 1: **Arus Kas** (PDF) — link ke existing financial PDF
- Card 2: **Stok & Komisi** (XLSX) — link ke existing stock + commission XLSX
- Card 3: **Daily Report** (PDF) — NEW, link ke `/laporan/daily`
- Card 4: **Inventory Movements** (XLSX) — NEW, link ke `/laporan/inventory-movements`

**Daily Report view (`laporan/daily.html`)** — sesuai image 1:
- **KOP klinik** (nama, alamat, kontak) — bisa di-edit via config
- **Title**: "DAILY REPORT NGI-SMD01 / KLINIK SIM-KK" + "PT [Nama Klinik]"
- **DAY**: "{Day name}" (e.g., "Tuesday") + **DATE**: "{tanggal}"
- **Bagian 1: CASH AT CASHIER** — modal awal hari (dari `daily_cash_float.modal_awal`)
- **Bagian 2: NET SALES** — list per kategori produk (Facial Wash, Sunscreen, Premium, dll) + total per kategori + **Total Sales**
- **Bagian 3: NET SALES (adj)** — Rounding, VAT, **Total Sales + Rounding + VAT**
- **Bagian 4: PENDAPATAN CARD (per metode non-tunai)** + **Non Cash Details** (detail tiap bank/EDC) → **Total Card**
- **Bagian 5: CASH DEPOSIT** + **ULPT & DP** + **Branch's Expend** + **RPJ** → totals
- **Bagian 6: Down Payment** + **Pelunasan** + **Total Other Branch's Expend** → **Total Expend**
- **Bagian 7: P n L** (prominent, display-md serif)
- **Bagian 8: CASH OUT (Tunai ke Transit)** + **End of day** + **Setoran Bank (Transit ke Bank)**
- **Tanda tangan dual (PRD 3.3.1)**:
  - Kanan bawah: **Mengetahui, [Nama Manajer]** dengan signature image
  - Di bawahnya: **([Nama Kasir])** dengan signature image
- **Workflow state badge** di top-right: `Draft` | `Submitted` | `Approved` | `Final`

**Inventory Movements view (`laporan/inventory-movements.html`)** — sesuai image 2:
- **Title**: "Inventory Movements" display-lg
- **Filter bar**: From (date) | To (date) | Branch (default "Klinik SIM-KK")
- **Result count** di kanan: "Total N results" caption
- **Table** per barang per hari:
  - Columns: `Item Code` | `Item Name` | `Beginning Balance` | `Purchase (IN)` | `Return Sales (IN)` | `Barang Masuk (IN)` | `Return Purchase (OUT)` | `Sales (OUT)` | `Real Sales (OUT)` | `Barang Keluar (OUT)` | `Ending Balance`
  - Striped minimal, monospaced numbers
  - Download XLSX button di top-right

## Static HTML Preview (BEFORE any Vue touch)

Sebelum implement Vue, generate **7 static HTML files** di `outputs/sim-kk-ui-previews/`:

| File | Layar | Interaksi ringan |
|---|---|---|
| `login.html` | Login full-bleed | Role chip click, password toggle |
| `pos.html` | POS bento | Add to cart, qty +/-, therapist avatar select, metode pill click |
| `rekam-medis.html` | Rekam medis kanvas | Tab switch (Catatan/Foto/History), textarea type |
| `gudang.html` | Gudang table | Filter chip click, row hover, drawer open |
| `laporan.html` | Laporan hub | 4 report card hover |
| `laporan-daily.html` | Daily Report | Date picker, status badge, TTD dual display |
| `laporan-inventory-movements.html` | Inventory Movements | Date range picker, table row hover, XLSX download button |

**Tech untuk preview**:
- Tailwind via CDN (`<script src="https://cdn.tailwindcss.com"></script>`)
- Custom config inline via `tailwind.config = {...}` script tag
- Google Fonts (Fraunces + Inter + JetBrains Mono) loaded dari CDN
- Foto dari Unsplash via direct URL
- Vanilla JS untuk interaksi ringan (tab switch, cart counter, drawer toggle, metode pill)
- **No Vue, no Vite, no API calls** — pure static

**Output dir**: `outputs/sim-kk-ui-previews/`
- 7 HTML files
- `README.md` (cara buka + design rationale + screenshot index)
- `assets/preview-{n}.png` (auto-generated via headless browser untuk review cepat)

**Review gate**: User buka di browser lokal. Saya kirim push notification setelah previews ready + screenshot untuk approval.

## Implementation Sequence (after preview approved)

**Phase A — Foundation (1 commit)**
- Update `tailwind.config.ts` dengan tokens baru
- Replace `tokens.css` jadi 1 baris `@import "tailwindcss"`
- Update `index.html` (font preconnect + meta)
- Verify `npm run dev` masih jalan

**Phase B — App shell + Login (1 commit)**
- New `AppShell.vue` (top nav 72px)
- Rewrite `LoginView.vue` jadi editorial layout
- Verify Playwright smoke masih pass

**Phase C — POS (1 commit)**
- Rewrite `PosView.vue` jadi bento
- Update `PaymentSummary.vue` (right rail sticky + metode pill)
- Verify POS end-to-end transaction (login → add service → pick therapist → pick metode → mark paid)

**Phase D — Rekam Medis (1 commit)**
- Rewrite `MedicalRecordView.vue` (tabs, autosave indicator)
- Update `Timeline.vue` + `PhotoCompare.vue` jadi editorial
- Verify upload foto + save treatment

**Phase E — Gudang (1 commit)**
- Rewrite `InventoryView.vue` (table-forward, filter, drawer)
- Verify purchase + FIFO display

**Phase F — Laporan (1 commit)**
- New `LaporanView.vue` (4-card hub)
- New `DailyReportView.vue` (typographic + dual TTD)
- New `InventoryMovementsView.vue` (table-forward)
- Update `ReportPreview.vue` jadi editorial
- Verify PDF/XLSX export (pakai backend existing + new services)

**Phase G — Polish + a11y (1 commit)**
- Reduce-motion check
- Focus rings (`focus-visible:ring-2 ring-forest ring-offset-2 ring-offset-cream`)
- Keyboard nav test
- Lighthouse score target ≥ 90 (perf, a11y, best practices, SEO)

**Total commits**: 7 (foundation + 5 view groups + polish)
**Total effort**: ~22-28 jam (lebih besar dari D1 karena ada 7 view redesign + 2 view baru)

## Acceptance Criteria

- [ ] 7 HTML previews di `outputs/sim-kk-ui-previews/` buka di browser & look "premium"
- [ ] User approve visual direction setelah preview
- [ ] `npm run dev` jalan tanpa error
- [ ] `npm run build` jalan tanpa error
- [ ] Playwright smoke test (`npm run test:smoke`) 4+ test pass
- [ ] Lighthouse score ≥ 90 di 4 kategori
- [ ] Reduced-motion: animasi disabled saat `prefers-reduced-motion: reduce`
- [ ] Mobile (375px, 768px, 1280px, 1440px) all readable
- [ ] Daily Report PDF dual TTD (PRD 3.3.1) — Kasir + Manajer
- [ ] Inventory Movements per barang per hari dengan 7 kolom IN/OUT
- [ ] HALLUCINATION.md update dengan 2026-06-05 entry
- [ ] Spec ini di-commit ke git

## Risks & Rollback

| Risk | Mitigation |
|---|---|
| Preview gate ditolak (user tidak suka aesthetic) | Easy rollback — previews are static, no Vue code touched yet |
| Tailwind utility-first terlalu "generic" | Tailwind tokens di-customize dengan palette editorial + type scale, bukan default |
| Font Fraunces tidak load (network issue) | Fallback ke `'GT Sectra', 'Tiempos Headline', 'Playfair Display', serif` di CSS stack |
| Lighthouse score < 90 | Defer JS non-critical, use `loading="lazy"` di foto, preconnect ke font CDN |
| Migrasi Daily Report + Inventory Movements menambah scope | Sudah include di spec, effort estimasi sudah update (22-28 jam dari 18-24) |

## Unknowns Locked by User (2026-06-06)

- Metode bayar: **single method per transaksi** (sesuai PRD varchar 32), 9 metode tersedia
- Tanda tangan Daily Report: **dual sign-off (Kasir + Manajer)** sesuai PRD 3.3.1
- Saldo awal kasir: **manual input via Buka Shift form** (tabel `daily_cash_float` baru) — belum di-confirm, default conservative
- Workflow Daily Report: `draft → submitted → approved → final`
