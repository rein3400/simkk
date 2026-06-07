# SIM-KK Deployment Guide — Railway

Target stack: Laravel 13 API + Vue 3 SPA on Railway, PostgreSQL on Railway Postgres, clinical photos on Cloudflare R2, WhatsApp Business Cloud API for notifications.

## 1. Infrastructure overview

```
                    ┌───────────────────────┐
                    │  Cloudflare (DNS+SSL) │
                    └──────────┬────────────┘
                               │
              ┌────────────────┼─────────────────┐
              ▼                                  ▼
   ┌──────────────────┐                ┌──────────────────┐
   │ apps/web (Vue 3) │                │ apps/api (Laravel)│
   │ Railway static   │  /api/* proxy  │ Railway web dyno  │
   └──────────────────┘                └─────────┬────────┘
                                                │
                          ┌─────────────────────┼─────────────┐
                          ▼                     ▼             ▼
                  ┌──────────────┐    ┌──────────────┐  ┌──────────┐
                  │  PostgreSQL  │    │ Cloudflare R2│  │  WA API  │
                  │   Railway    │    │  (foto klin) │  │  Meta    │
                  └──────────────┘    └──────────────┘  └──────────┘
```

Monthly cost estimate (Samarinda clinic, low traffic):

| Service | Plan | Cost |
|---|---|---|
| Railway web (Laravel) | Hobby 5 USD | $5 |
| Railway Postgres | Starter 5 USD | $5 |
| Railway static (Vue) | Free | $0 |
| Cloudflare R2 | Free 10 GB | $0 |
| Cloudflare DNS + SSL | Free | $0 |
| Meta WA Cloud API | Free 1000 conv/mo | $0 |
| **Total** | | **~$10/mo** |

## 2. One-time setup

### 2.1 Cloudflare R2 (clinical photos)

1. Cloudflare dashboard → R2 → Create bucket `simkk-clinical`
2. Generate R2 API token: Account → R2 → Manage R2 API Tokens → Create token (Object Read & Write scoped to `simkk-clinical`)
3. Note: Account ID, Access Key, Secret Key
4. R2 endpoint: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`

### 2.2 Meta WhatsApp Business

1. Create Meta Business Account: business.facebook.com
2. Create WhatsApp Business App: developers.facebook.com → Create App → Business → WhatsApp
3. Add phone number (the clinic's WA number)
4. Get from App dashboard:
   - Phone number ID
   - WhatsApp Business Account ID
   - Permanent system user access token
5. Send a test message from Postman / curl to confirm template-less text message works

### 2.3 Buy domain (optional)

`sim-kk.example.id` ~$10-15/year. Add to Cloudflare for free DNS + SSL.

## 3. Railway setup

### 3.1 Create project

1. railway.app → New Project → Deploy from GitHub
2. Select the sim-kk repo

### 3.2 Add services

**Service 1 — API (Laravel):**
- Root directory: `apps/api`
- Build: `composer install --no-dev --optimize-autoloader`
- Start: `php artisan serve --host=0.0.0.0 --port=$PORT`
- Generate public domain in Railway settings

**Service 2 — Web (Vue 3):**
- Root directory: `apps/web`
- Build: `npm ci && npm run build`
- Start: `npx serve dist -l $PORT -s`
- Generate public domain in Railway settings

**Service 3 — Postgres:**
- New → Database → PostgreSQL
- Railway auto-provisions `DATABASE_URL` on the API service

## 4. Environment variables

Set on the **API** service in Railway:

```bash
APP_NAME=SIM-KK
APP_ENV=production
APP_KEY=base64:...                # from `php artisan key:generate --show`
APP_URL=https://api.sim-kk.example.id
FRONTEND_URL=https://sim-kk.example.id
SANCTUM_STATEFUL_DOMAINS=sim-kk.example.id,www.sim-kk.example.id
SESSION_DOMAIN=.sim-kk.example.id

# Database (auto-set by Railway Postgres)
DB_CONNECTION=pgsql
DATABASE_URL=postgresql://...     # auto-injected

# Cloudflare R2
STORAGE_DISK=r2
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_BUCKET=simkk-clinical
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_REGION=auto

# WhatsApp
WHATSAPP_TOKEN=EAAB...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_BUSINESS_ACCOUNT_ID=...

# Clinic branding
CLINIC_NAME="KLINIK KECANTIKAN SIM-KK"
CLINIC_ADDRESS="Jl. Klinik No. 1, Samarinda"
```

Set on the **Web** service:
```bash
VITE_API_URL=https://api.sim-kk.example.id
```

## 5. First deploy

1. Push to `main` → Railway auto-deploys both services
2. SSH into API service (or use Railway shell):
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   ```
3. If migrating from local prototype photos:
   ```bash
   php artisan simkk:migrate-photos-to-r2
   ```

## 6. Custom domain

In Cloudflare DNS:
- `sim-kk.example.id` CNAME → `<web>.up.railway.app` (proxied)
- `api.sim-kk.example.id` CNAME → `<api>.up.railway.app` (proxied)

In Railway service settings → Domains → Add custom domain.

## 7. Backups

- Railway Postgres: automatic daily backups (7-day retention on Starter)
- R2: enable bucket versioning in Cloudflare dashboard
- App code: GitHub

## 8. Monitoring (optional)

- Railway metrics: built-in
- Error tracking: add Sentry (`composer require sentry/sentry-laravel`)
- Uptime: UptimeRobot (free) → ping `/api/health`

## 9. Local dev vs prod matrix

| Setting | Local | Production |
|---|---|---|
| DB | sqlite `database/database.sqlite` | pgsql via `DATABASE_URL` |
| Storage | `local` (filesystem) | `r2` |
| APP_DEBUG | true | false |
| WhatsApp | unset (logs warning) | set from Meta |
| CORS | permissive localhost | `FRONTEND_URL` only |

## 10. Rollback

- Railway: service → Deployments → redeploy previous
- DB: `railway run php artisan migrate:rollback --step=1` (one migration)
- Photos: R2 versioning → restore previous object
