# 06 — Cloudflare DNS, WAF, Caching (15 min)

## A. Beli + add domain

1. Cloudflare → **Account Home** → **Register Domains**
2. Search: `sim-kk` (atau nama lain yang lo mau) di TLD `.id` (Rp 200-300k) atau `.com` ($10)
3. **Purchase** — bayar via CC. Aktivasi ~5 menit.
4. Domain auto-add ke akun Cloudflare.

## B. DNS Records

**Cloudflare → DNS → Records**:

| Type | Name | Target | Proxy | TTL |
|---|---|---|---|---|
| CNAME | `sim-kk` | `sim-kk-web.pages.dev` | ✅ Proxied | Auto |
| CNAME | `api` | `<TUNNEL_ID>.cfargotunnel.com` | ✅ Proxied | Auto |
| (opsional) CNAME | `cdn` | `simkk-clinical.r2.cloudflarestorage.com` | ✅ Proxied | Auto (kalau pakai R2 custom domain) |

## C. SSL/TLS

**Cloudflare → SSL/TLS → Overview**:
- Mode: **Full (Strict)** ← penting
- Edge Certificates → **Always Use HTTPS**: ON
- Minimum TLS Version: **1.2**
- Enable **HSTS** (Strict-Transport-Security):
  - Max Age: 12 months
  - Include subdomains: ON
  - No-sniff: ON
  - Preload: ON (opsional, submit ke https://hstspreload.org)

## D. WAF — Rate Limiting Rules (Free tier: 1 rule)

**Cloudflare → Security → WAF → Rate limiting rules → Create rule**:

### Rule 1: Login brute-force protection
- Rule name: `Login rate limit`
- Match: `http.request.uri.path eq "/api/login"` AND `http.request.method eq "POST"`
- Rate: **5 requests per 10 seconds per IP**
- Action: **Block for 600 seconds**
- Order: 1

### Rule 2 (opsional, kalau paket Pro): Generic API abuse
- Match: `http.request.uri.path eq "/api/transactions/pay"`
- Rate: 30 req/min per IP
- Action: Challenge

## E. Caching

**Cloudflare → Caching → Cache Rules → Create rule**:

### Rule 1: Cache static Vue assets
- Name: `Cache Vue static`
- Match: `http.request.uri.path.extension in {"js" "css" "woff" "woff2" "png" "jpg" "jpeg" "webp" "svg"}`
- Action: **Cache eligible** + Edge TTL: 1 month + Browser TTL: 1 day

### Rule 2: NO cache for API
- Name: `No cache API`
- Match: `http.request.uri.path eq "/api/*"` (atau `starts_with "/api/"`)
- Action: **Bypass cache**

## F. Auto Minify + Image optimization

**Speed → Optimization**:
- Auto Minify: HTML ✅, CSS ✅, JS ✅
- **Polish** (image optimization): Lossy + WebP (free tier)
- **Mirage** (lazy load images for mobile): ON
- **Early Hints**: ON

## G. Bot Protection (opsional)

**Security → Bots**:
- Free tier: **Bot Fight Mode** (toggle ON)
- Block known bad bots, challenge可疑 user agents

## H. Email Routing (free)

Kalau lo mau `admin@sim-kk.example.id` → forward ke Gmail:
1. **Email → Email Routing** → **Add record**
2. Custom address: `admin@sim-kk.example.id` → Destination: `klien@gmail.com`
3. Verify destination (Gmail)

## Verifikasi

```bash
# 1. Pages loading
curl -I https://sim-kk.example.id
# Expected: HTTP/2 200, server: cloudflare

# 2. API health
curl https://api.sim-kk.example.id/api/health
# Expected: {"ok":true}

# 3. CORS check
curl -H "Origin: https://sim-kk.example.id" -H "Access-Control-Request-Method: POST" \
     -X OPTIONS https://api.sim-kk.example.id/api/login -i
# Expected: access-control-allow-origin: https://sim-kk.example.id

# 4. Rate limit trigger
for i in {1..10}; do
  curl -X POST https://api.sim-kk.example.id/api/login \
    -H "Content-Type: application/json" \
    -d '{"username":"x","password":"x"}' -o /dev/null -w "%{http_code}\n"
done
# Expected: first 5 = 401, rest = 403 (rate limited)
```

## Lanjut

Lanjut step 07-verify.md untuk end-to-end testing 4 role + WAF triggers.
