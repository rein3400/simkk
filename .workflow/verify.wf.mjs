export const meta = {
  name: 'simkk-verify-synth',
  description: 'Adversarial verify per finding + final synthesis report',
  phases: [
    { title: 'Adversarial Verify' },
    { title: 'Synthesize' },
  ],
}

const WF = 'D:/users/stefa/project/sim-kk/.workflow'
const API = 'http://127.0.0.1:8000'

phase('Adversarial Verify')

const REFUTER_RCE = [
'You are an ADVERSARIAL REFUTER. Your job is to ATTEMPT TO DISPROVE these findings.',
'Default to refuted=true unless the reproduction is irrefutable.',
'',
'Findings to refute:',
'',
'1. WB-2 / BB-2 / GB-004 — Photo upload accepts .php, .exe, .svg, path traversal; stored on public disk.',
'   - WB-2 claim: GET /storage/clinical/.../shell.php returns rendered phpinfo HTML (PHP executed).',
'   - GB-004 claim: PHP does NOT execute under default mime map (static serving).',
'   - These CONFLICT. Reproduce on LIVE server http://127.0.0.1:8000:',
'     a) Login as Terapis (token in .workflow/recon.json).',
'     b) POST /api/patients/1/photos with filename=shell.php and any small file body. (Do NOT include the literal PHP tag in your prompt; use any non-PHP payload to test extension + serving.)',
'     c) Find the saved file path: ls apps/api/storage/app/public/clinical/ to confirm storage. Or compute the URL from the response objectRef.',
'     d) curl the URL: http://127.0.0.1:8000/storage/clinical/RM-2026-0018/<UUID>-shell.php',
'     e) If body = raw file bytes (PHP source) -> not RCE, downgrade to High stored-payload. If body = rendered PHP output (RCE) -> Critical.',
'   - Also test .svg with embedded <script> tag — does the server return it with image/svg+xml mime? Persistent XSS via SVG.',
'   - Test path traversal: filename like ../../../foo.jpg — does the file escape clinical dir?',
'   - Test .html / .htm / .js — these get auto-executed in browsers.',
'',
'2. WB-3 / BB-3 / GB-003 — Stored XSS via metode_bayar / supplier.',
'   - WB-3: metode_bayar accepts SCRIPT-tag payload (26 chars, max:32).',
'   - GB-003: supplier field same.',
'   - Verify FE sanitization: read apps/web/src/views/PosView.vue, InventoryView.vue, ReportsView.vue. Search for v-html, innerHTML, dangerouslySetInnerHTML. If FE uses v-text or mustache {{ }} interpolation, Vue auto-escapes -> NO XSS. If v-html found, CONFIRMED.',
'',
'Output JSON to .workflow/verify_rce_xss.json as array.',
'For each finding: refuted (true/false), downgrade (null or severity change), reproduction_evidence (actual HTTP response excerpt or file:line), refutation_evidence (if refuted), remediation.',
'Be BRUTAL — if a bug only triggers on misconfigured prod (e.g. nginx mimetype change), say so.'
].join('\n')

const REFUTER_LOGIN = [
'You are an ADVERSARIAL REFUTER. ATTEMPT TO DISPROVE these:',
'',
'1. Login 500 on missing level/role (WB-1, BB-12, GB-001).',
'   - Claim: POST /api/login with only {username,password} -> 500 with full stack trace.',
'   - Reproduce: curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" -d \'{"username":"kasir","password":"simkk-2026"}\'',
'   - If body has exception, file, trace fields with file paths -> CONFIRMED CWE-209.',
'   - If body is just {message:...} -> refute, generic 500 not stack leak.',
'   - Note: APP_DEBUG=true in apps/api/.env amplifies this. Even if env-bounded, dev/staging is exposed.',
'',
'2. Stock insufficient returns 500 (BB-1, GB-002).',
'   - Claim: POST /api/transactions/pay with qty > stock -> 500 with stack trace.',
'   - Reproduce: as Kasir (token in .workflow/recon.json), POST /api/transactions/pay items=[{serviceId:6, qty:999}].',
'   - If 500 + stack: CWE-754 + CWE-209. CONFIRMED High.',
'   - Refute angle: read apps/api/bootstrap/app.php and apps/api/app/Exceptions/Handler.php. If RuntimeException is mapped to 422 via a global handler, refute. If unhandled, confirm.',
'',
'Output JSON to .workflow/verify_login_stock.json.'
].join('\n')

const REFUTER_BUSINESS = [
'You are an ADVERSARIAL REFUTER. ATTEMPT TO DISPROVE these:',
'',
'1. BB-19 — Finance saldo drift Rp520.000.',
'   - Claim: bootstrap laporan finance shows saldo=12.305.000, sum(transaksi.total)=12.825.000, diff 520.000.',
'   - Reproduce: as Manajer, GET /api/bootstrap. Walk finance rows + sum balance column. Then SQL: SELECT SUM(total) FROM transaksi.',
'   - If real diff: read BootstrapController::buildReports to find the bug. Sum of BukuKas.Debit - Kredit should equal sum(transaksi.total). If they differ, there are orphan BukuKas rows or Transaksi without BukuKas entries (atomicity failure).',
'   - Severity: if real -> Critical (financial integrity).',
'',
'2. BB-15 — Empty PDF finance report.',
'   - Claim: PDF has valid header but content is empty FlateDecode stream.',
'   - Reproduce: GET /api/reports/finance/export as Manajer, save to a file. Open in browser or run: powershell -c "Get-Content file.pdf -Encoding Byte -TotalCount 4".',
'   - Also check: do other PDFs (e.g. create a custom one with one row) render content? Or is dompdf broken for all?',
'   - Refute angle: PDF has 0 BukuKas rows so empty is correct. Query: SELECT COUNT(*) FROM buku_kas. If > 0 and PDF is empty, real bug.',
'',
'3. GB-006 — Terapis impersonation in treatment notes.',
'   - Claim: Terapis can write treatment note attributing it to any therapist name.',
'   - Reproduce: read apps/api/app/Http/Controllers/Api/RekamMedisController.php. Does it accept terapis name from request body, or pull from $request->user()->name / id? If from auth user -> refute. If from request body with no validation -> CONFIRMED.',
'',
'4. WB-4 / BB-9 — /pay race condition on id_transaksi.',
'   - Claim: 2 concurrent /pay in same second produce same id_transaksi, second insert hits unique constraint -> 500.',
'   - Reproduce: use PowerShell Start-Job to fire 5 parallel /pay calls. Count 201 vs 500 responses.',
'   - On SQLite (serialized) the race may be masked. Try writing 2 transactions within a tight loop and measure.',
'   - If 5 fires -> 1 wins, 4 fail with 500 -> CONFIRMED race. If 5 fires -> 5 succeed -> refute.',
'',
'Output JSON to .workflow/verify_business.json.'
].join('\n')

const verifyResults = await parallel([
  () => agent(REFUTER_RCE, { label: 'refuter:RCE+XSS', phase: 'Adversarial Verify', model: 'sonnet' }),
  () => agent(REFUTER_LOGIN, { label: 'refuter:login+stock500', phase: 'Adversarial Verify', model: 'sonnet' }),
  () => agent(REFUTER_BUSINESS, { label: 'refuter:business+integrity', phase: 'Adversarial Verify', model: 'sonnet' }),
])

log('Refutation pass complete. Synthesizing...')

phase('Synthesize')

const SYNTH = [
'You are the FINAL SYNTHESIZER.',
'',
'Read these files:',
'- .workflow/whitebox.md (14 findings)',
'- .workflow/blackbox.md (23 findings)',
'- .workflow/graybox.md (25 findings)',
'- .workflow/verify_rce_xss.json',
'- .workflow/verify_login_stock.json',
'- .workflow/verify_business.json',
'',
'Tasks:',
'1. Cross-confirm dedup: if WB-1 == BB-12 == GB-001, they are ONE finding. Merge, cite all sources.',
'2. Apply refuter verdicts:',
'   - refuted=true with hard evidence -> drop, move to rejected section.',
'   - refuted=false but downgrade -> adjust severity.',
'   - refuted=false with full confirmation -> keep, upgrade if irrefutable.',
'3. Rank by severity: Critical > High > Medium > Low > Info.',
'4. For each surviving finding output: ID (F-NNN), Title, Severity, Module (Auth/Transaksi/FIFO/RekamMedis/Inventaris/Laporan/WhatsApp/Contract/Integrity), Root cause (1 sentence), Evidence (HTTP excerpt or file:line), Reproduction steps, Fix (working code patch 3-10 lines), Effort (S/M/L), PRD section violated, Source lanes (WB/BB/GB IDs), Refuter verdict.',
'',
'Output 2 files:',
'- .workflow/findings.md — human-readable, ranked, exec summary at top',
'- .workflow/findings.json — machine-readable, same data',
'',
'Top-level structure of findings.md:',
'',
'# SIM-KK Test Sweep — Final Report',
'',
'**Generated**: 2026-06-04',
'**Source lanes**: 3 (White Box, Black Box, Gray Box)',
'**Refutation passes**: 3',
'**Server under test**: Laravel 13.8 + sqlite, seed users (simkk-2026)',
'**Total raw findings**: 62 (14 WB + 23 BB + 25 GB)',
'**After dedup + refutation**: N findings',
'',
'## Executive Summary',
'5-10 bullets, Critical + High only.',
'',
'## Critical (must-fix before any deployment)',
'## High (fix before next user test)',
'## Medium (fix in next sprint)',
'## Low (track in backlog)',
'## Info / Disproven (logged for completeness)',
'## Rejected by Refuter',
'## PRD Compliance Matrix',
'',
'Rules:',
'- Terse. Max 150 words per finding. No prose padding.',
'- Drop every finding refuter confirmed REFUTED with hard evidence.',
'- Fix must be a working code patch, not "should consider".',
'- No fabricated findings.',
'- Merge duplicates.',
'Return both file paths.'
].join('\n')

const synth = await agent(SYNTH, { label: 'Synthesize', phase: 'Synthesize', model: 'sonnet' })

return { verify: verifyResults, synth }
