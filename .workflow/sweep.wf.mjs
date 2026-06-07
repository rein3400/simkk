export const meta = {
  name: 'simkk-test-sweep',
  description: 'White/black/gray box testing sweep on SIM-KK Laravel API',
  phases: [
    { title: 'White Box' },
    { title: 'Black Box' },
    { title: 'Gray Box' },
    { title: 'Adversarial Verify' },
    { title: 'Synthesize' },
  ],
}

const API = 'http://127.0.0.1:8000'
const WF  = 'D:/users/stefa/project/sim-kk/.workflow'

// Phase 1: White Box — full source access, internal logic + boundary
phase('White Box')
const wb = await agent(`You are a WHITE BOX tester with FULL READ ACCESS to SIM-KK source at D:/users/stefa/project/sim-kk/.
Server is LIVE at http://127.0.0.1:8000 (Laravel 13 + sqlite). 4 seed users (kasir/terapis/gudang/manajer, password 'simkk-2026').
Recon + tokens + bootstrap data already in: ${WF}/recon.json — READ IT FIRST.

## Mission
Find bugs that REQUIRE source visibility. Internal logic, business rules, validation gaps, atomicity issues.

## Target files (read these)
- apps/api/app/Services/TransaksiService.php  (FIFO + komisi + DB::transaction)
- apps/api/app/Services/InventarisService.php
- apps/api/app/Http/Controllers/Api/* (all 7)
- apps/api/app/Http/Middleware/RequireRole.php
- apps/api/database/migrations/2026_06_01_*.php  (all 12)
- apps/api/database/seeders/BatchStokSeeder.php (known batch data: produk 1 has BS-0426-A qty=12 exp 2026-09-12, BS-0526-B qty=22 exp 2027-01-20; produk 2 DS-1225-X qty=5 exp 2026-06-30 [OLDEST], DS-0526-Y qty=13 exp 2027-02-12; etc)
- apps/api/database/seeders/LayananSeeder.php (10 layanan, products #6/7/8 have stok_produk_id set; #1-5 treatments, #9-10 paket with no stock)

## Test angles (use PHP tinker, raw SQL via php artisan tinker, or PowerShell Invoke-RestMethod)
1. **FIFO order correctness**: pay for layanan#6 (stok_produk_id=1) qty=15 — should take 12 from BS-0426-A + 3 from BS-0526-B (asc expiry). Verify decrementStock hits batches in correct order, never skips to non-FIFO.
2. **FIFO NULL expiry sentinel**: produk 5 (LED-0126, expiry NULL) is "Reusable". Should be LAST in batch order, not first. Add new batch with expiry '2026-12-31' for produk 5, verify LED-0126 still sorts last.
3. **FIFO oversell**: try to pay qty=200 for produk#6 (max 34 total: 12+22) — should throw RuntimeException with "tidak cukup" message, NOT partial decrement + 500. Verify ZERO buku_kas row was created.
4. **Komisi immutability snapshot**: pay normally → record Transaksi.komisi_total and TransaksiDetail.nilai_komisi. Then UPDATE layanan SET komisi_rate=0.99 WHERE id=X. Hit /api/bootstrap again — commission should be UNCHANGED (snapshot frozen at pay time).
5. **DB::transaction atomicity**: pay for a non-existent serviceId (e.g. 9999). Per TransaksiService: Layanan::findOrFail runs BEFORE DB::transaction. But what if stock decrement fails mid-loop? Inject by paying for a valid layanan with stok_produk_id set, but force a failure after first item resolves (modify test to set qty higher than all stock combined across multiple items). Verify NO orphan: Transaksi row, BukuKas row, or TransaksiDetail row.
6. **requireRole bypass attempts**:
   - Send Authorization header with tampered token (Bearer 1|fake_token)
   - Send no Authorization at all
   - Send malformed bearer
7. **Login level/role null**: POST /api/login with {username, password} (no level, no role). Controller: $validated['level'] ?? $validated['role'] returns NULL. Then User::where('level', null) → SQL error → 500. CONFIRMED in preflight. Document the fix.
8. **Input validation boundary**:
   - diskon = -50000 (negative) → controller has min:0 validation, expect 422
   - diskon = 999999999 (exceeds subtotal) → service does min($subtotal, max(0, $diskon)) — should clamp to subtotal
   - qty = 0 in items array → service does max(1, qty) — should treat as 1, not 0
   - items contains duplicates: same serviceId twice with qty=1 → 2 separate TransaksiDetail rows + double stock decrement
   - metode_bayar = '<script>alert(1)</script>' (32+ chars) → max:32 validation, expect 422
   - pasien_id = 99999 (non-existent) → exists:pasien,id validation, expect 422
9. **Cash ledger invariant**: after N transactions, sum of all Debit - sum of all Kredit = sum of Transaksi.total - sum of refunds. Bootstrap /reports/finance computes saldo by walking BukuKas. Verify it matches a direct SQL SUM query.
10. **NoSQL injection but yes SQL safety**: ' OR 1=1 -- as username — should still 401, no auth bypass.
11. **Race on /pay**: fire 2 concurrent POST /api/transactions/pay for the same cart, see if duplicate Transaksi rows get same id_transaksi. (The id is generated from count()+1 BEFORE create — race possible.) Document if not handled.
12. **Pay with item that has stok_produk_id but stock=0**: decrementStock finds no batches with qty>0, available=0, throws. Verify no Transaksi / BukuKas created.
13. **Foto MIME spoof**: POST /api/patients/{id}/photos with a file named shell.php containing '<?php phpinfo(); ?>' as JPG. What does StorageService do? (Read StorageService.php first.)
14. **Per-role all-allowed**: with manajer token, hit ALL routes — pay (allowed), treatments (allowed), photos (allowed), inventory/purchases (allowed), reports/export (allowed), whatsapp/* (allowed). All should NOT 403.

## Output
Write findings to ${WF}/whitebox.md as a JSON array (top-level array) with schema:
[{ "id": "WB-1", "title": "...", "severity": "Critical|High|Medium|Low|Info", "module": "Auth|Tx|FIFO|Komisi|...", "file": "apps/api/...", "lines": "L1-L10", "repro": "powershell -c ...", "expected": "...", "actual": "...", "fix": "concrete code suggestion", "status": "CONFIRMED|DISPROVEN|UNVERIFIED" }]

CRITICAL RULES:
- Every finding MUST be reproduced live. No "I think" — actual HTTP response or tinker output.
- Re-verify by checking the source code at the cited file:line matches your claim.
- If a test passes (no bug), log it as Info with status=DISPROVEN and a note "expected failure, got pass — invariant holds".
- Mark status=UNVERIFIED only when you can't reproduce (e.g. env limitation), with a clear reason.
- Be terse. No prose. Pure data. Max 200 words per finding.

Return the FULL path to the written file at the end.`, {
  label: 'White Box',
  phase: 'White Box',
  model: 'sonnet',
})

// Phase 2: Black Box — external only, no source access during test design
phase('Black Box')
const bb = await agent(`You are a BLACK BOX tester. NO source access during test design. Treat the system as opaque.
Server: http://127.0.0.1:8000. 4 seed users (kasir/terapis/gudang/manajer, password 'simkk-2026'). Tokens in ${WF}/recon.json (read it for tokens + bootstrap, but DO NOT read any apps/api/app/** source).

## Mission
Find bugs that surface from behavior alone — missing 4xx codes, wrong shapes, broken UX flows, missing error messages, status code violations.

## 4 role E2E flows (must all execute end-to-end)

### Flow 1 — Kasir POS happy path
- Login as kasir
- POST /api/bootstrap (verify 200 + valid shape)
- POST /api/transactions/pay with 1 layanan (treatment, no stock) + 1 produk (with stock) + 1 terapis
- Verify response 201, contains transaction.id matching "TRX-YYYYMMDD-NNN", receipt.id "RCPT-..."
- Re-hit /bootstrap, verify new Transaksi row appears, BatchStok qty decremented correctly (compare to seed)
- GET /api/reports/finance/export with kasir token → 403 (Manajer only)
- GET /api/reports/finance/export with manajer token → 200 + PDF (verify first 4 bytes = "%PDF")

### Flow 2 — Terapis Rekam Medis
- Login as terapis
- POST /api/patients/1/treatments with keluhan/tindakan JSON
- POST /api/patients/1/photos with a multipart upload (use Invoke-WebRequest -Form). Try a real PNG header (89 50 4E 47). Try a file renamed .php→.jpg
- Hit bootstrap, verify treatment + photo row appear
- Try POST /api/transactions/pay with terapis token → 403
- Try POST /api/inventory/purchases with terapis token → 403

### Flow 3 — Gudang Inventaris
- Login as gudang
- POST /api/inventory/purchases with a new batch: produk_id=1, qty=50, hpp=95000, kadaluarsa='2026-12-15', supplier='Test Supplier'
- Verify bootstrap inventory for produk 1 now has 3 batches, sorted by expiry ASC, "firstOut":true on earliest
- Try POST /api/transactions/pay with gudang token → 403
- Try POST /api/patients/1/treatments with gudang token → 403

### Flow 4 — Manajer Reports
- Login as manajer
- GET /api/reports/finance/export → save body, verify %PDF header, verify file size > 1KB
- GET /api/reports/stock/export → save body, verify PK (ZIP) header (xlsx), unzip with Expand-Archive to a temp dir, check sheet names
- GET /api/reports/commission/export → verify xlsx, check column headers include "Nama Terapis", "Gaji Pokok", "Take Home Pay" or current row shape
- POST /api/transactions/pay with manajer token (allowed) — pay 1 paket, verify komisi_total = round(harga * 0.11)

## Cross-flow probes
- After all flows, hit bootstrap once more, verify total Transaksi count = 3 (seed) + N (your pays) — should be exact.
- Verify cash_ledger total = sum of all your Transaksi.total
- Try invalid UUID/token shape: Bearer abc.def.ghi
- Try SQL injection in serviceId/items[].serviceId: 1 OR 1=1, 1; DROP TABLE transaksi; --
- Try XSS in catatan: <img src=x onerror=alert(1)>
- Try very long fields: keluhan 1MB string
- Try rapid 5x /pay for same cart — race?

## Output
Write findings to ${WF}/blackbox.md as JSON array (same schema as whitebox).
Same rules: CONFIRMED requires actual repro output. UNVERIFIED only if env blocks.
Note: source access at TEST TIME is fine for VERIFICATION post-discovery, but design tests from spec + observed behavior first.

Return the file path.`, {
  label: 'Black Box',
  phase: 'Black Box',
  model: 'sonnet',
})

// Phase 3: Gray Box — API source-aware, HTTP-side testing
phase('Gray Box')
const gb = await agent(`You are a GRAY BOX tester. Source-visible, but tests run over HTTP — you're probing the API contract, not unit-testing.
Server: http://127.0.0.1:8000. Tokens + bootstrap in ${WF}/recon.json.

## Mission
Find bugs at the seam: API contract vs FE types, IDOR, race conditions, hidden trust assumptions.

## Source files to read for contract reasoning
- apps/api/app/Http/Controllers/Api/TransaksiController.php (request validation rules)
- apps/api/app/Http/Controllers/Api/BootstrapController.php (response shape, snake_case vs camelCase)
- apps/web/src/types/domain.ts (FE type contract — User.nama_lengkap, Patient.alamat, role "Admin", etc)
- apps/api/app/Services/StorageService.php (R2-aware, local fallback)
- apps/api/app/Services/AuditService.php
- apps/api/app/Http/Controllers/Api/WhatsAppController.php

## Test angles

1. **Contract mismatch — snake_case vs camelCase**:
   - FE type User has 'nama_lengkap?' alias. Bootstrap returns 'name' (mapped from nama_lengkap in controller). Login response has 'nama_lengkap' (NOT mapped). Inconsistent? Verify.
   - FE Patient has 'alamat?' optional. BootstrapController does NOT include alamat in patient map. So FE receives patient without alamat. If FE code accesses patient.alamat, it gets undefined. Document.
   - FE Role type has "Admin" — does any seed user have level='Admin'? (Per HALLUCINATION.md, frontend added "Admin" to Role type. Is this enforced by backend?)

2. **IDOR — patient records**:
   - Login as Terapis-A. POST /api/patients/1/treatments — works.
   - Login as Terapis-B. POST /api/patients/1/treatments — should also work (Terapis is not bound to specific patients). Verify backend does NOT check therapist-patient assignment.
   - If FE shows only "my patients" but backend has no such filter, that's a spec gap (not a bug per se, but document).
   - GET /api/patients/1/treatments does NOT exist (only POST). What does FE do to fetch history? Probably re-bootstrap. Document the missing GET endpoint as a UX gap.

3. **IDOR — patient list exposure**:
   - Login as Kasir. Hit /api/bootstrap. Verify Kasir sees ALL patients (no filter). PRD implies patient is data subject, not user. So exposing all patients to all roles might be OK, but document.

4. **Authorization edge — manajer in /pay**:
   - Manajer role is allowed in 'role:Kasir,Manajer' middleware. Document: can Manajer do everything Kasir can? Yes per code. Is this a separation-of-duties violation per the PRD business rules? PRD doesn't forbid it.

5. **Race on /pay — concurrent same cart**:
   - Fire 2 simultaneous POST /api/transactions/pay with identical body. Check if both succeed and create 2 Transaksi rows. Both will have same id_transaksi pattern (TRX-...-count+1) due to non-atomic count+create.
   - Verify: does SQLite serialize writes? Yes (WAL or default). But the count+create race is in PHP, not DB. Document.
   - Test: use PowerShell Start-Job to fire 5 parallel /pay calls.

6. **Login race / token reuse**:
   - Login, get token T1. Logout, token deleted. Try to reuse T1 — should 401.
   - Login twice, get T1 + T2. Logout deletes only current. T1 still valid? Document Sanctum behavior.

7. **Response leak — user enumeration**:
   - POST /api/login with bad username — 401. With bad password for valid username — 401. Same message? If different, that's user enumeration. (Read AuthController: same message regardless, "Username, password, atau level salah". Good.)

8. **Error message verbosity**:
   - Trigger a 500 (the no-level login). What's the response body? Does it leak stack trace? (Laravel debug mode might.) Inspect.
   - Laravel APP_DEBUG=true in local — does it leak file paths in error responses?

9. **Missing endpoints**:
   - No GET /api/patients/{id} — only bootstrap has them
   - No PATCH/PUT for transaksi
   - No DELETE for treatments
   - No refund flow (BukuKas Kredit only happens via pay, no way to refund)
   - Document gaps as Info findings.

10. **WhatsApp /whatsapp/reminder** (read WhatsAppController first to know shape):
    - Try as Kasir (allowed per route)
    - Try as Gudang (NOT in middleware role list — should 403)
    - Try with invalid pasien_id, missing phone, etc.

11. **Stock edge — qty that exactly matches first batch**:
    - Barrier Serum layanan_id=6 → produk_id=1 → BS-0426-A qty=12. So pay 12 of Barrier Serum. Should fully deplete batch 1, leave 22 in batch 2.
    - Verify the batch with qty=0 does NOT appear in subsequent bootstrap (filter: where qty > 0).
    - Verify next pay rolls to next batch.

12. **Date/timezone bugs**:
    - id_transaksi format uses now()->format('ymd'). If 2 transactions on same day, count+1. If midnight crosses, both get same ymd. Race in 1-second window. Document.
    - waktu field is H:i only — no seconds. Same idempotency risk.

## Output
Write findings to ${WF}/graybox.md as JSON array (same schema).
Return file path.`, {
  label: 'Gray Box',
  phase: 'Gray Box',
  model: 'sonnet',
})

log('All 3 lanes launched. Awaiting results...')
return { whitebox: wb, blackbox: bb, graybox: gb }
