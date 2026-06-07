# 05 — Cloudflare Tunnel (VPS → Internet tanpa buka port)

Cloudflare Tunnel = cara expose VPS ke internet TANPA buka port 80/443, tanpa static IP, plus dapet Cloudflare proxy + WAF gratis.

## Steps

### A. Login + create tunnel

1. Di **VPS** (SSH ke 128.199.45.67), jalankan:
   ```bash
   cloudflared tunnel login
   ```
   Output: URL `https://...trycloudflare.com/...` — buka di browser, pilih domain lo (mis. `example.id` — kalau belum ada, skip dulu, return ke step 6 dulu)

   **Kalau belum ada domain di Cloudflare**:
   - Lanjut step 6 (beli domain), lalu kembali ke sini
   - Atau pakai **quick tunnel** (temporary URL `*.trycloudflare.com`, no domain needed):
     ```bash
     cloudflared tunnel --url http://localhost:80
     ```
     Akan generate URL random seperti `https://random-word-random.trycloudflare.com`. Bagus untuk testing, tapi **URL berubah tiap restart** — jangan pakai untuk production.

2. Create named tunnel:
   ```bash
   cloudflared tunnel create simkk-api
   ```
   Output: file `/home/simkk/.cloudflared/<TUNNEL_ID>.json` + cert di `cert.pem`
   Catat `<TUNNEL_ID>`.

3. Buat config file:
   ```bash
   mkdir -p ~/.cloudflared
   cat > ~/.cloudflared/config.yml <<'EOF'
   tunnel: <TUNNEL_ID>
   credentials-file: /home/simkk/.cloudflared/<TUNNEL_ID>.json

   ingress:
     # API subdomain → VPS nginx
     - hostname: api.sim-kk.example.id
       service: http://localhost:80
     # Catch-all (required)
     - service: http_status:404
   EOF
   ```

4. Test config:
   ```bash
   cloudflared tunnel info simkk-api
   cloudflared tunnel run simkk-api &
   # Tunggu 5 detik, lalu:
   curl -H "Host: api.sim-kk.example.id" http://127.0.0.1/api/health
   # Expected: {"ok":true}
   ```

5. Route DNS di Cloudflare dashboard:
   - **DNS** → Records → **Add record**
   - Type: `CNAME`
   - Name: `api`
   - Target: `<TUNNEL_ID>.cfargotunnel.com`
   - Proxy: **Proxied** (orange cloud ON)
   - Klik **Save**

6. Kill foreground tunnel:
   ```bash
   pkill -f "cloudflared tunnel run"
   ```

7. Install as systemd service (auto-restart on reboot):
   ```bash
   sudo cloudflared service install
   sudo systemctl enable cloudflared
   sudo systemctl start cloudflared
   sudo systemctl status cloudflared
   ```

8. Verify dari external:
   ```bash
   curl https://api.sim-kk.example.id/api/health
   # Expected: {"ok":true}
   ```

## Lanjut

Lanjut step 06-dns-waf.md untuk DNS records + WAF rate limiting rules.
