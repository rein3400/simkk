# 01 — Cloudflare R2 Setup (15 min)

R2 = S3-compatible object storage untuk foto klinis Before/After.

## Steps (UI)

1. Login ke https://dash.cloudflare.com
2. Sidebar → **R2 Object Storage** → **Create bucket**
   - Name: `simkk-clinical`
   - Location: **Automatic** (recommended) atau `APAC`
   - Klik **Create bucket**
3. Catat **Account ID** (di pojok kanan R2 dashboard, atau URL `/<account_id>/r2/...`)
4. Sidebar → **R2** → **Manage R2 API Tokens** → **Create API token**
   - Token name: `simkk-app`
   - Permissions: **Object Read & Write**
   - Bucket: `simkk-clinical` (scoped)
   - TTL: (no expiry)
   - Klik **Create API Token**
5. Catat (hanya muncul SEKALI):
   - **Access Key ID** → `R2_ACCESS_KEY_ID`
   - **Secret Access Key** → `R2_SECRET_ACCESS_KEY`
   - **Endpoint** (form: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`) → `R2_ENDPOINT`

## Public Access (PENTING)

Ada 2 mode. Pilih sesuai FE contract:

### Opsi A: Private + signed URL (recommended untuk HIPAA/UU PDP)
- Bucket: tetap **Private**
- FE minta signed URL via endpoint baru `/api/clinical-photos/{id}` (route ada di `apps/api/routes/api.php` — TAPI belum ada controller, perlu di-tambah saat ini mode diaktifkan)
- TTL signed URL: 5-15 menit
- Saya recommend opsi ini.

### Opsi B: Public bucket
- Bucket settings → **Public access** → Allow
- Custom domain: optional `cdn.sim-kk.example.id` (pakai R2 custom domain)
- FE langsung load `<objectRef>` via `https://cdn.sim-kk.example.id/<key>`
- Trade-off: tidak ada access control di object level. Tapi R2 sudah private-by-default, jadi foto tidak bisa di-browse langsung tanpa URL.

**Default**: mulai dari Opsi B untuk setup cepat, migrate ke A nanti kalau butuh ACL.

## Tambah custom domain (Opsi B)

1. R2 → bucket `simkk-clinical` → Settings → **Public access** → **Connect domain**
2. Masukkan `cdn.sim-kk.example.id` (atau subdomain lain)
3. Cloudflare otomatis tambah CNAME record + provision SSL
4. URL pattern: `https://cdn.sim-kk.example.id/clinical/RM-2026-0018/<uuid>-test.png`

## Verifikasi

```bash
# Install rclone (opsional, untuk test manual)
# Atau pakai AWS CLI:
aws s3 ls s3://simkk-clinical/ \
  --endpoint-url https://<ACCOUNT_ID>.r2.cloudflarestorage.com \
  --access-key-id $R2_ACCESS_KEY_ID \
  --secret-access-key $R2_SECRET_ACCESS_KEY

# Upload test
echo "test" > /tmp/x.txt
aws s3 cp /tmp/x.txt s3://simkk-clinical/test/ \
  --endpoint-url https://<ACCOUNT_ID>.r2.cloudflarestorage.com
aws s3 ls s3://simkk-clinical/test/
```

## Env Vars (untuk `.env` Laravel)

```env
STORAGE_DISK=r2
R2_ACCESS_KEY_ID=<dari token>
R2_SECRET_ACCESS_KEY=<dari token>
R2_BUCKET=simkk-clinical
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_REGION=auto
R2_USE_PATH_STYLE_ENDPOINT=true
```

Files Laravel yang sudah baca env ini: `apps/api/config/filesystems.php` (disk `r2` config), `apps/api/app/Services/StorageService.php`.

## Cleanup

Kalau pakai Opsi B, tambahkan `.htaccess`-equivalent di R2 untuk tolak eksekusi PHP:

R2 → bucket → Settings → **CORS** + **Object lifecycle** rules. Set lifecycle: delete objects older than X years sesuai retention policy klinik (UU PDP Indonesia: 5 tahun untuk rekam medis).
