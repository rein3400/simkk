# 04 — Cloudflare Pages (Vue 3 SPA) (10 min)

## Option A: Connect via GitHub (recommended untuk auto-deploy on push)

1. Push project ke GitHub:
   ```bash
   cd D:\users\stefa\project\sim-kk
   git init  # kalau belum
   git add -A
   git commit -m "Initial commit"
   # Buat repo di https://github.com/new, lalu:
   git remote add origin https://github.com/<YOUR_USER>/sim-kk.git
   git push -u origin master  # atau main
   ```
2. Cloudflare dashboard → **Workers & Pages** → **Create application** → **Pages** → **Connect to Git**
3. Pilih repo `sim-kk`
4. Configure build:
   - **Project name**: `sim-kk-web`
   - **Production branch**: `master` (atau `main`)
   - **Build command**: `npm run build`
   - **Build output directory**: `dist`
   - **Root directory**: `apps/web`
5. Klik **Save and Deploy**
6. Setelah deploy sukses, set environment variable:
   - **Settings → Environment variables** → **Add variable**
   - Variable: `VITE_API_URL`
   - Value: `https://api.sim-kk.example.id/api` (atau URL yang lo pilih)
   - Environment: **Production**
7. **Trigger redeploy** (deploy lama pake VITE_API_URL kosong)

## Option B: Direct Upload (kalau gak mau pakai GitHub)

1. Build lokal:
   ```bash
   cd D:\users\stefa\project\sim-kk\apps\web
   npm install
   $env:VITE_API_URL="https://api.sim-kk.example.id/api"
   npm run build
   ```
2. Cloudflare → **Pages** → **Create application** → **Pages** → **Upload assets**
3. Project name: `sim-kk-web`
4. Drag-drop folder `dist/`
5. Klik **Deploy site**

> Note: Direct upload = no auto-deploy. Setiap perubahan harus re-upload manual.

## Verifikasi

1. Setelah deploy, Cloudflare kasih URL default: `https://sim-kk-web.pages.dev`
2. Buka di browser
3. Coba login dengan `kasir` / `simkk-2026` — kalau berhasil dan bootstrap data load, FE + BE sudah connected
4. **Kalau error CORS**: edit `apps/api/.env`, set `FRONTEND_URL=https://sim-kk-web.pages.dev`, lalu restart nginx (SSH → `sudo systemctl reload nginx`)

## Custom Domain (optional, setelah domain dibeli)

1. **Custom domains** → **Set up a custom domain** → masukkan `sim-kk.example.id`
2. Cloudflare auto-add CNAME, SSL auto-provision
3. Update `VITE_API_URL` di Pages env var kalau API domain juga berubah

## Lanjut

Lanjut ke step 05-tunnel.md untuk expose API via Cloudflare Tunnel.
