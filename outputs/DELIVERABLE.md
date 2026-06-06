# SIM-KK v0.1 — Client Preview Package

**Tanggal**: 2026-06-06
**Project**: Sistem Informasi Manajemen Klinik Kecantikan (SIM-KK)
**Dipersembahkan untuk**: Klien Pengusaha Klinik Kecantikan, Samarinda
**Status**: Visual Preview v0.1 — menunggu persetujuan klien

---

## Elevator Pitch

SIM-KK adalah aplikasi kasir + rekam medis + gudang + laporan manajer yang dirancang khusus untuk klinik kecantikan skala UMKM — berjalan di cloud, otomatis hitung komisi terapis, stok keluar dengan sistem FIFO, dan laporan kas siap ekspor ke PDF dan Excel.

---

## What's in the Box

Paket ini berisi **7 layar HTML statis** yang bisa dibuka langsung di browser. Setiap layar adalah mockup visual dengan aesthetic Editorial Luxury (cream + deep forest + serif). Bukan aplikasi berjalan — ini adalah referensi visual untuk disetujui sebelum tim mulai implementasi penuh.

### Daftar 7 Layar

| # | Layar | File |
|---|---|---|
| 1 | Login | `login.html` |
| 2 | Point of Sale (POS) | `pos.html` |
| 3 | Rekam Medis | `rekam-medis.html` |
| 4 | Gudang | `gudang.html` |
| 5 | Laporan (hub) | `laporan.html` |
| 6 | Laporan Arus Kas | `laporan-arus-kas.html` |
| 7 | Laporan Stok & Komisi Terapis | `laporan-stok-komisi.html` |

### Deskripsi Singkat Tiap Layar

**1. Login** — Halaman masuk dengan layout split editorial: foto klinik di sisi kiri, formulir email dan kata sandi di sisi kanan dengan chip pilihan peran interaktif (Kasir, Terapis, Gudang, Manajer). Setiap peran akan mengarahkan ke tampilan default yang berbeda.

**2. Point of Sale (POS)** — Layar utama kasir dengan 6 tile layanan kecantikan, panel keranjang di kanan, dan pill bar untuk metode pembayaran (Tunai, QRIS, Transfer, Kartu). Dilengkapi avatar picker terapis yang mengunci kepemilikan komisi sebelum transaksi ditutup.

**3. Rekam Medis** — Antarmuka ramah tablet dengan tab Catatan, Foto, dan History. Catatan treatment ditulis di kanvas luas dengan autosave, foto before/after diunggah sebagai potret, dan timeline kunjungan sebelumnya tampil dalam format serif italic.

**4. Gudang** — Tabel inventaris dengan filter chip (Aman, Menipis, Prioritas, Expired), garis-garis zebra untuk keterbacaan, dan slide-in drawer di kanan untuk detail batch dan riwayat pembelian supplier.

**5. Laporan (hub)** — Empat kartu laporan utama yang ditata sebagai galeri: Arus Kas, Stok & Komisi, Daily Report, dan Inventory Movements. Klik kartu untuk masuk ke laporan spesifik.

**6. Laporan Arus Kas** — Laporan PDF format hitam-putih utilitarian dengan KOP klinik, judul, kolom ID Transaksi, Debit, Kredit, Saldo, dan kolom tanda tangan Manajer + Kasir di pojok kanan bawah sesuai eksplisit PRD 3.3.1.

**7. Laporan Stok & Komisi Terapis** — Laporan XLSX dengan 6 kolom (ID Pegawai, Nama Terapis, Jumlah Tindakan, Total Nominal Komisi, Gaji Pokok, Grand Total Take-Home Pay) sesuai PRD 3.3.2 — terotomatisasi dan tidak bisa dimanipulasi.

---

## Arsitektur Sistem

```
+------------------------------------------------------------------+
|                    VPS (Laravel + Vue 3)                         |
|         Backend API  +  Frontend SPA + Auth + Reports            |
+------+---------------+----------------+----------------+--------+
       |               |                |                |
       v               v                v                v
+-------------+  +-------------+  +-------------+  +-------------+
| Cloudflare  |  | Cloudflare  |  | Cloudflare  |  |  Telegram   |
|     D1      |  |     R2      |  |   Workers   |  |  Webhook    |
|  (Database) |  |  (Storage)  |  |  (Edge)     |  | (Notifikasi)|
+-------------+  +-------------+  +-------------+  +-------------+
   Transaksi,      Foto klinis,    Static assets,   Reminder
   rekam medis,    tanda tangan,   CDN global,      pasien,
   inventory,      bukti bayar,    rate limiting    alert owner
   komisi          PDF laporan                     stok tipis
```

**Penjelasan singkat**:
- **VPS Laravel + Vue 3** — server utama menjalankan API dan aplikasi web. Anda login dari browser seperti biasa.
- **Cloudflare D1** — database SQLite di cloud, cepat dan murah. Menyimpan semua data transaksi, rekam medis, stok, dan komisi.
- **Cloudflare R2** — tempat menyimpan foto klinis, tanda tangan, dan PDF laporan. Tidak ada batasan ukuran yang mengganggu.
- **Telegram Webhook** — bot yang mengirim notifikasi otomatis ke pasien (janji temu, aftercare) dan ke owner (stok menipis, laporan harian).

---

## 4 Pertanyaan Kritis yang Butuh Approval

Sebelum tim mulai implementasi penuh, kami butuh jawaban Anda untuk empat pertanyaan ini. Tanpa kepastian di area ini, kami tidak bisa lanjut ke kode.

### 1. Aesthetic Direction
Apakah Anda puas dengan arah visual **Editorial Luxury** (warna cream, aksen deep forest, tipografi serif italic)? Atau Anda punya referensi visual lain (misalnya dari klinik yang Anda kagumi) yang lebih cocok dengan brand klinik Anda?

### 2. Logo Klinik dan Identitas
Kami menggunakan placeholder "KLINIK KECANTIKAN SIM-KK" di semua mockup. Bisa kirimkan logo final, nama klinik, alamat lengkap, dan nomor telepon yang akan dicetak di KOP laporan dan kop surat?

### 3. Telegram Bot Token
Untuk mengaktifkan notifikasi otomatis ke pasien (WhatsApp atau Telegram), kami memerlukan bot token. Apakah Anda sudah punya akun Telegram Business atau lebih memilih WhatsApp Business API? Tim kami akan memandu setup-nya.

### 4. Data Sample vs Data Asli
Mockup menggunakan nama terapis contoh (Rani, Dewi, Sari, Maya) dan produk skincare generic. Untuk implementasi nyata, apakah Anda akan menyediakan master data (daftar layanan, harga, terapis, supplier) atau Anda ingin tim kami yang membantu input?

---

## Timeline Estimasi

Setelah Anda menyetujui keempat pertanyaan di atas dan menyerahkan material yang dibutuhkan:

| Tahap | Durasi | Output |
|---|---|---|
| Migrasi database + deploy D1 | 1.5 – 2 hari | Database live, sample data masuk |
| Rewrite Vue untuk 7 layar | 3 – 4 hari | Aplikasi web interaktif |
| Daily Report + tanda tangan | 1 hari | Workflow approval + upload TTD |
| Polish, aksesibilitas, CI | 1 hari | Lulus uji otomatis, rapi |

**Total: ~7 – 8 hari kerja** dari persetujuan Anda hingga aplikasi siap dipakai di klinik.

---

## Kontak

**Project Manager**: [Nama PM] — [email] — [nomor WhatsApp]
**Developer Lead**: [Nama Dev] — [email] — [nomor WhatsApp]
**Alamat Kantor**: [Alamat studio/kantor]

---

**Next step**: Setelah Anda reply ke-4 pertanyaan di atas, kami kickoff implementasi pada hari kerja berikutnya. Preview statis ini tinggal landasan — yang sebenarnya baru mulai dibangun setelah Anda bilang "setuju".
