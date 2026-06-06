[
  {
    "id": "GB-001",
    "title": "Login tanpa 'level' atau 'role' return 500 + full stack trace + filesystem path",
    "severity": "HIGH",
    "category": "Information Disclosure",
    "cwe": "CWE-209 (Generation of Error Message Containing Sensitive Information)",
    "endpoint": "POST /api/login",
    "file": "apps/api/app/Http/Controllers/Api/AuthController.php:23",
    "evidence": "curl -X POST /api/login -d '{\"username\":\"kasir\",\"password\":\"simkk-2026\"}' returns HTTP 500 with body containing 'Undefined array key \"role\"', file path 'D:\\\\Users\\\\stefa\\\\Project\\\\SIM-KK\\\\apps\\\\api\\\\app\\\\Http\\\\Controllers\\\\Api\\\\AuthController.php', full Laravel stack trace.",
    "repro_steps": [
      "POST /api/login with valid username+password but omit both 'level' and 'role' fields",
      "Inspect response: HTTP 500 + 'exception', 'file', 'line', 'trace' array"
    ],
    "impact": "APP_DEBUG=true leaks server filesystem layout, Laravel version, and project root path. Attacker can chain this with directory-traversal vulns (GB-006).",
    "recommendation": "Fix line 23: use `?? null` or `?? $request->input('level', $request->input('role'))`. Also set APP_DEBUG=false in production.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-002",
    "title": "TransaksiService: 500 dengan stack trace pada insufficient stock",
    "severity": "HIGH",
    "category": "Information Disclosure + Business Logic",
    "cwe": "CWE-209 + CWE-754 (Improper Check for Unusual or Exceptional Conditions)",
    "endpoint": "POST /api/transactions/pay",
    "file": "apps/api/app/Services/TransaksiService.php:147",
    "evidence": "curl POST /pay items=[{serviceId:6,qty:99}] returns 500 with 'message: Stok FIFO tidak cukup...', 'exception: RuntimeException', 'file: D:\\\\...\\\\TransaksiService.php', full trace.",
    "repro_steps": [
      "Authenticate as Kasir",
      "POST /api/transactions/pay with items qty exceeding stock"
    ],
    "impact": "Stack trace leaks internal service path. Also: this is a domain error, should map to 409 Conflict or 422 Unprocessable Entity, not 500.",
    "recommendation": "Throw a custom domain exception (e.g. InsufficientStockException extends DomainException) caught by handler and rendered as 422/409.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-003",
    "title": "Stored XSS via addPurchase supplier field",
    "severity": "HIGH",
    "category": "Stored XSS / Injection",
    "cwe": "CWE-79 (Improper Neutralization of Input During Web Page Generation)",
    "endpoint": "POST /api/inventory/purchases",
    "file": "apps/api/app/Http/Controllers/Api/InventarisController.php:14-25",
    "evidence": "POST /inventory/purchases with supplier='<script>alert(1)</script>' returns 201. Bootstrap GET /api/bootstrap returns the exact string verbatim inside inventory[].batches[].supplier.",
    "repro_steps": [
      "Login as Gudang",
      "POST /api/inventory/purchases body: {\"produk_id\":1,\"supplier\":\"<script>alert(1)</script>\",\"kode_batch\":\"XSS-1\",\"qty\":1,\"hpp\":1,\"kadaluarsa\":\"2026-12-15\"}",
      "Bootstrap returns: \"supplier\": \"<script>alert(1)<\\/script>\""
    ],
    "impact": "Any FE that renders BootstrapController inventory supplier value with v-html or unescaped interpolation gets persistent XSS. Auditor would pop the dashboard with one POST.",
    "recommendation": "Server: validate supplier against /^[^<>]{1,60}$/. FE: ensure sanitization on render. Also reject HTML control chars in catatan/terapis/judul/title (RekamMedisController accepts unconstrained strings).",
    "status": "VERIFIED"
  },
  {
    "id": "GB-004",
    "title": "Photo upload accepts arbitrary extension (.php, .exe, .svg) — content stored on public disk",
    "severity": "HIGH",
    "category": "Unrestricted File Upload",
    "cwe": "CWE-434 (Unrestricted Upload of File with Dangerous Type)",
    "endpoint": "POST /api/patients/{patient}/photos",
    "file": "apps/api/app/Services/StorageService.php:13-23",
    "evidence": "Terapis POST /patients/1/photos with filename='shell.php' returns 201; file written to apps/api/storage/app/public/clinical/RM-2026-0018/{uuid}-shell.php. Same for shell.exe, .svg, ../etc/passwd. Verified via `ls` and HTTP GET /storage/clinical/... returning the file content (200).",
    "repro_steps": [
      "Login as Terapis",
      "POST /api/patients/1/photos with filename='shell.php' and a base64 image body",
      "GET http://host/storage/clinical/RM-2026-0018/{uuid}-shell.php — file returned with 200, no Content-Type restriction"
    ],
    "impact": "Stored payload of arbitrary type. PHP doesn't execute under nginx+php-fpm default mime map (static serving returns as binary) but: (a) extension is preserved and could be served as text/plain in misconfig; (b) .svg with embedded JS executes when opened in browser; (c) .html, .htm, .js files would execute; (d) attacker can DoS disk by uploading 9999999999 batches (see GB-009).",
    "recommendation": "Reject anything not matching `\\.(png|jpg|jpeg|webp|heic)$` via `mimes:png,jpg,jpeg,webp`. Also enforce max file size and image content sniffing (getimagesize). Move clinical photos to `private` disk (R2) and stream via signed URL.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-005",
    "title": "addPurchase path traversal in kode_batch (../../etc/passwd stored)",
    "severity": "MEDIUM",
    "category": "Path Traversal",
    "cwe": "CWE-22 (Improper Limitation of a Pathname to a Restricted Directory)",
    "endpoint": "POST /api/inventory/purchases",
    "file": "apps/api/app/Http/Controllers/Api/InventarisController.php:14-25",
    "evidence": "POST /inventory/purchases with kode_batch='../../etc/passwd' returns 201; bootstrap returns \"code\": \"../../etc/passwd\". Same on photo filename field (sanitized to '..-..-..-etc-passwd' there, but on inventory it remains raw '../../etc/passwd').",
    "repro_steps": [
      "Login as Gudang",
      "POST /api/inventory/purchases kode_batch='../../etc/passwd'",
      "Bootstrap confirms raw traversal path stored"
    ],
    "impact": "Currently no file write/read goes through kode_batch, so direct RCE/InfoDisclosure not present. But: (a) XSS payload can use it (e.g. kode_batch='</td><script>...'); (b) any future export/report that echoes this path into a file or PDF will land outside intended dir; (c) violates secure-by-default and pollutes reports UI.",
    "recommendation": "Add regex: `regex:/^[A-Za-z0-9._-]{1,30}$/` for kode_batch. Same for filename in addPhoto (currently `StorageService` strips non-alnum, but inventory controller does NOT).",
    "status": "VERIFIED"
  },
  {
    "id": "GB-006",
    "title": "Terapis dapat mencatat treatment atas nama terapis lain (impersonation)",
    "severity": "HIGH",
    "category": "IDOR / Authorization",
    "cwe": "CWE-639 (Authorization Bypass Through User-Controlled Key)",
    "endpoint": "POST /api/patients/{patient}/treatments",
    "file": "apps/api/app/Http/Controllers/Api/RekamMedisController.php:14-40",
    "evidence": "Login as Terapis (dr. Melati, id=2). POST /patients/1/treatments with body `{\"terapis\":\"Sinta Ayu\",\"judul\":\"Fake\",\"catatan\":\"I am not Sinta\"}` returns 201 with therapist='Sinta Ayu' recorded. Backend does not check (a) which therapist the logged-in user is bound to, (b) whether the 'terapis' string matches a real Terapis row, (c) whether the terapis is the requester.",
    "repro_steps": [
      "Login as terapis",
      "POST /api/patients/1/treatments {terapis:'Sinta Ayu', judul:'X', catatan:'impersonation'}",
      "Response 201; bootstrap shows therapist='Sinta Ayu' for the new treatment"
    ],
    "impact": "Audit trail integrity broken. Therapist commission attribution to wrong person → financial fraud. Patient safety: medical notes written under another clinician's name.",
    "recommendation": "(a) Resolve Terapis from auth user, not from request body. (b) If `terapis` must be a string, validate it against Terapis::nama. (c) Refuse if Terapis user is not assigned to the patient (per PRD 'my patients').",
    "status": "VERIFIED"
  },
  {
    "id": "GB-007",
    "title": "Terapis tidak di-bound ke patient assignment — Terapis-A dan Terapis-B keduanya bisa tulis patient mana pun",
    "severity": "MEDIUM",
    "category": "IDOR / Spec Gap",
    "cwe": "CWE-285 (Improper Authorization)",
    "endpoint": "POST /api/patients/{patient}/treatments + /photos",
    "file": "apps/api/app/Http/Controllers/Api/RekamMedisController.php:14-72",
    "evidence": "Login as any Terapis → POST /patients/{any_id}/treatments → 201. TIDAK ada check `pasien.assigned_terapis_id == auth_user.terapis_id`. Note: User table has no terapis_id column, so this would require a join table. Currently the entire Terapis role is a flat ACL with no per-patient scoping.",
    "repro_steps": [
      "Login as terapis (id=2)",
      "POST /api/patients/6/treatments (patient 6 = Citra Ananda, not assigned to dr. Melati)",
      "201 created"
    ],
    "impact": "Any Terapis can write clinical notes to any patient record. May be acceptable per PRD, but: (a) FE must not show 'my patients' filter that backend can't enforce; (b) audit log must clearly identify the writer — currently uses auth user_id, which is OK; (c) chain with GB-006 = identity forgery.",
    "recommendation": "Document explicit decision. If PRD wants per-patient assignment, add `pasien.assigned_terapis_id` or pivot table `pasien_terapis` and check in controller.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-008",
    "title": "Bootstrap tidak include 'alamat' field untuk Patient",
    "severity": "MEDIUM",
    "category": "Contract Mismatch",
    "cwe": "CWE-1059 (Insufficient Technical Documentation)",
    "endpoint": "GET /api/bootstrap",
    "file": "apps/api/app/Http/Controllers/Api/BootstrapController.php:22-44",
    "evidence": "FE type `Patient.alamat?: string` (apps/web/src/types/domain.ts:21) is declared as PRD field. BootstrapController patient map has keys [id,name,age,phone,recordId,concern,lastVisit,riskNote,treatments,photos] — `alamat` MISSING. If FE code dereferences patient.alamat, it gets `undefined`.",
    "repro_steps": [
      "Login any role",
      "GET /api/bootstrap",
      "Inspect patients[0] — no 'alamat' key"
    ],
    "impact": "Type contract drift. FE 'PRD field added' comment (HALLUCINATION.md) but backend never wired. UI will silently show empty/null. Privacy risk: PRD implies patient address; if missing here, address is in DB but not surfaced — confirm intentional.",
    "recommendation": "Decide: either (a) add `'alamat' => $p->alamat` to BootstrapController map, or (b) drop `alamat?` from FE type until feature ships. Do not leave 'documented but not implemented'.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-009",
    "title": "addPurchase tidak ada max qty — qty=9999999999 diterima",
    "severity": "MEDIUM",
    "category": "Input Validation / DoS",
    "cwe": "CWE-20 (Improper Input Validation)",
    "endpoint": "POST /api/inventory/purchases",
    "file": "apps/api/app/Http/Controllers/Api/InventarisController.php:18",
    "evidence": "POST body {qty:9999999999} returns 201. totalStock inflated to 10,000,000,059. Verified via subsequent /bootstrap. SQLite integer is 64-bit so no overflow, but FE / reports will sum/format it.",
    "repro_steps": [
      "Login as Gudang",
      "POST /api/inventory/purchases {qty:9999999999, ...}",
      "GET /api/bootstrap → inventory[0].totalStock = 10000000059"
    ],
    "impact": "UI/report corruption. Stock summary becomes meaningless. One user can spam huge qtys repeatedly. No correction endpoint exists.",
    "recommendation": "Add `max:1000000` to qty rule. Add an admin endpoint to void/adjust stock.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-010",
    "title": "BootstrapController returning 'nama_pasien' as 'name' but Login response uses 'nama_lengkap' — inconsistent shape",
    "severity": "LOW",
    "category": "Contract Mismatch",
    "cwe": "CWE-1059",
    "endpoint": "POST /api/login vs GET /api/bootstrap",
    "file": "apps/api/app/Http/Controllers/Api/AuthController.php:39-46 vs BootstrapController.php:86-92",
    "evidence": "Login returns `{user: {id, username, nama_lengkap, level, shift}}` — no 'name'. Bootstrap returns `{users: [{id, username, name, role, shift}]}` — no 'nama_lengkap'. FE type User declares BOTH `name: string` and `nama_lengkap?: string` (domain.ts:9-11). For login response, FE must read `nama_lengkap`; for bootstrap, must read `name`. This is the contract drift HALLUCINATION.md warns about.",
    "repro_steps": [
      "POST /api/login → user.nama_lengkap = 'Nadia Putri' (no .name)",
      "GET /api/bootstrap → users[0].name = 'Nadia Putri' (no .nama_lengkap)"
    ],
    "impact": "FE must branch per endpoint. Risk: a generic `mapUser()` helper fails silently for one of the two shapes.",
    "recommendation": "Pick ONE canonical name. Either: (a) bootstrap also returns `nama_lengkap`, or (b) login maps `nama_lengkap → name` like bootstrap does. Recommend (b) so FE can drop the union field.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-011",
    "title": "FE Role type includes 'Admin' but no backend user has level='Admin'",
    "severity": "LOW",
    "category": "Spec Gap / Type Drift",
    "cwe": "CWE-1059",
    "endpoint": "n/a (type-level)",
    "file": "apps/web/src/types/domain.ts:1",
    "evidence": "Role = 'Kasir' | 'Terapis' | 'Gudang' | 'Manajer' | 'Admin'. UserSeeder seeds only 4 users, none with level='Admin'. Login as 'admin'/'Admin' returns 401 'Username, password, atau level salah.' No seeded Admin user, no role check that would distinguish Admin from Manajer.",
    "repro_steps": [
      "POST /api/login {username:'admin', password:'x', level:'Admin'}",
      "Response 401; verified no Admin user exists in DB"
    ],
    "impact": "Dead code on FE. If a developer adds an Admin-only UI branch, it will never match anyone. Confusing in handover.",
    "recommendation": "Either remove 'Admin' from Role union until feature ships, OR seed an Admin user with appropriate permissions (e.g. role:Manajer and an is_admin flag for superuser actions).",
    "status": "VERIFIED"
  },
  {
    "id": "GB-012",
    "title": "Empty items array returns 422 'field is required' instead of 'array min:1'",
    "severity": "LOW",
    "category": "Validation Message Bug",
    "cwe": "CWE-1059",
    "endpoint": "POST /api/transactions/pay",
    "file": "apps/api/app/Http/Controllers/Api/TransaksiController.php:17",
    "evidence": "Validation rule is `'items' => 'required|array|min:1'`. With `items:[]` Laravel returns 422 'The items field is required.' (misleading — items IS present, just empty).",
    "repro_steps": [
      "POST /api/transactions/pay {items:[]}",
      "Response: {\"message\":\"The items field is required.\",\"errors\":{\"items\":[\"The items field is required.\"]}}"
    ],
    "impact": "Misleading error: FE shows 'items required' when actually it's 'items must not be empty'. UX papercut; debug-time confusion.",
    "recommendation": "Either change rule to `'present|array|min:1'` (or `array|min:1` if items is nullable in another path), or just live with Laravel's behavior and document it.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-013",
    "title": "TransaksiService.pay menghasilkan komisi meski total=0 (ghost commission)",
    "severity": "MEDIUM",
    "category": "Business Logic",
    "cwe": "CWE-840 (Business Logic Errors)",
    "endpoint": "POST /api/transactions/pay",
    "file": "apps/api/app/Services/TransaksiService.php:42, 72",
    "evidence": "POST /pay items=[{serviceId:1,qty:1}] diskon=999999999 returns 201 with `total: 0, discount: 285000, commission: 34200, cashLedger: {type:'Debit', amount:0}`. The commission 34200 is recorded against a 0-total sale.",
    "repro_steps": [
      "Login as Kasir",
      "POST /api/transactions/pay {items:[{serviceId:1,qty:1}], diskon:999999999}",
      "Response commission=34200, total=0"
    ],
    "impact": "Therapist commission calculated on subtotal, not total. Klinik pays commission on a sale that produced Rp0 cash. Audit/tax risk.",
    "recommendation": "Recompute `lineKomisi` against `subtotal - lineDiscountShare`, or zero out komisi when total=0. Or: commission only when `status='Lunas'` AND `total>0`.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-014",
    "title": "TransaksiService id_transaksi generator non-atomic (count+1 race)",
    "severity": "MEDIUM",
    "category": "Race Condition / ID Generation",
    "cwe": "CWE-362 (Concurrent Execution / Race Condition)",
    "endpoint": "POST /api/transactions/pay",
    "file": "apps/api/app/Services/TransaksiService.php:58-59",
    "evidence": "Line 58: `$count = (int) Transaksi::count() + 1;` then `sprintf('TRX-%s-%03d', now()->format('ymd'), $count)`. In a multi-process PHP-FPM environment, two requests arriving in the same ymd window can read the same count, generate identical id_transaksi. With SQLite default serialization, this didn't reproduce in 5-parallel curl, but the design is unsafe. Also: `waktu` is H:i only (no seconds), so two payments in the same minute have the same time field — no idempotency.",
    "repro_steps": [
      "5 parallel POST /pay with identical body — 5/5 returned distinct TRX-260604-042..046 (SQLite serialized)",
      "But: under Postgres/MySQL or higher concurrency, the same could collide. The ymd-format + count+1 has no UNIQUE constraint fallback — DB would fail or accept duplicate on second insert."
    ],
    "impact": "If/when DB is migrated off SQLite, ID collisions are possible. Idempotency gap: a client retrying in the same minute will create two transactions.",
    "recommendation": "Use UUID v4 or a DB sequence. Add UNIQUE constraint on `id_transaksi`. Add a `client_request_id` field for idempotency. Keep `waktu` as full timestamp.",
    "status": "PARTIAL (design risk; not currently exploited due to SQLite serialization)"
  },
  {
    "id": "GB-015",
    "title": "Sanctum logout hanya kill current token — old tokens tetap valid (by design, but undocumented)",
    "severity": "INFO",
    "category": "Spec Gap",
    "cwe": "n/a",
    "endpoint": "POST /api/logout",
    "file": "apps/api/app/Http/Controllers/Api/AuthController.php:49-53",
    "evidence": "Login twice as same user → get T1 and T2. Logout using T1. T1 returns 401, T2 still returns 200. This is Sanctum's default and arguably correct, but if PRD intends 'logout everywhere', this fails silently.",
    "repro_steps": [
      "Login as kasir twice → TOK1, TOK2",
      "POST /api/logout with TOK1",
      "GET /api/bootstrap with TOK2 — still 200"
    ],
    "impact": "Compromised token stays valid if user only logs out from one device. For a klinik, low risk, but document or implement `tokens()->delete()` for 'logout all'.",
    "recommendation": "Add `POST /api/logout-all` that deletes all tokens for user. Document Sanctum behavior in API docs.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-016",
    "title": "Login response dengan valid user+pw tapi wrong level returns 401 (no enumeration, but...)",
    "severity": "INFO",
    "category": "Info Disclosure (negative)",
    "cwe": "CWE-204 (Observable Response Discrepancy)",
    "endpoint": "POST /api/login",
    "file": "apps/api/app/Http/Controllers/Api/AuthController.php:25-30",
    "evidence": "Bad username + bad password: 401 'Username, password, atau level salah.'. Bad password (valid user) + correct level: 401 same message. Good. However: query is `User::where('username', X)->where('level', Y)` — if level is wrong but user exists, query returns null, message is same. If username is bad, query returns null, message is same. No enumeration possible from this endpoint.",
    "repro_steps": [
      "POST {username:'nobody', password:'x', level:'Kasir'} → 401 same msg",
      "POST {username:'kasir', password:'WRONG', level:'Kasir'} → 401 same msg"
    ],
    "impact": "Good — no user enumeration.",
    "recommendation": "Maintain. Add rate limiting to /login to prevent brute force.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-017",
    "title": "addTreatment tidak ada max length untuk catatan (1MB accepted)",
    "severity": "LOW",
    "category": "Input Validation / DoS",
    "cwe": "CWE-20",
    "endpoint": "POST /api/patients/{patient}/treatments",
    "file": "apps/api/app/Http/Controllers/Api/RekamMedisController.php:19-23",
    "evidence": "Rule: `'catatan' => 'required|string'` — no max. POST with catatan=1MB of 'A' returns 201. The bootstrap response then includes that full 1MB string in `treatments[].notes`.",
    "repro_steps": [
      "Login as Terapis",
      "POST /api/patients/1/treatments with catatan=1MB of 'A'",
      "201; subsequent /bootstrap returns 1MB string"
    ],
    "impact": "One Terapis can poison the bootstrap response size for all roles. With pagination absent, mobile clients will OOM on /bootstrap. No rate limit on this endpoint.",
    "recommendation": "Add `max:5000` (or whatever clinical note max is). Add pagination to /bootstrap.patients[].treatments or filter to last N.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-018",
    "title": "BootstrapController::buildInventory tidak ada pagination — query N+1 pada photos/treatments",
    "severity": "LOW",
    "category": "Performance / DoS",
    "cwe": "CWE-400 (Uncontrolled Resource Consumption)",
    "endpoint": "GET /api/bootstrap",
    "file": "apps/api/app/Http/Controllers/Api/BootstrapController.php:19-44",
    "evidence": "Pasien::with(['treatments', 'photos'])->get() returns ALL patients with all relations. With GB-017 poisoning, single bootstrap call fetches all unbounded notes/photos. No LIMIT, no chunk.",
    "repro_steps": [
      "After GB-017, GET /api/bootstrap — response size > 5MB, all in one JSON",
      "RekamMedisController has no DELETE endpoint, so data accumulates forever"
    ],
    "impact": "Memory bloat on server, slow FE, no way to delete mistakes.",
    "recommendation": "Limit treatments/photos to recent N (e.g. last 20 per patient). Add DELETE /patients/{id}/treatments/{tid} and /photos/{pid} for corrections.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-019",
    "title": "No DELETE for treatments, photos, transactions, batches — no way to correct mistakes",
    "severity": "LOW",
    "category": "Missing Endpoint / Spec Gap",
    "cwe": "n/a",
    "endpoint": "n/a (missing)",
    "file": "apps/api/routes/api.php (full enumeration)",
    "evidence": "Routes file has POST only for treatments/photos/purchases/pay. No PUT/PATCH/DELETE. A misrecorded treatment (e.g. impersonation in GB-006) cannot be deleted via API — only via direct DB. Same for transactions (no refund/adjust flow).",
    "repro_steps": [
      "POST /api/patients/1/treatments {terapis:'Sinta Ayu', judul:'Fake', catatan:'X'} (GB-006)",
      "No DELETE endpoint exists"
    ],
    "impact": "Clinical data integrity: cannot remove an erroneous note; audit trail will be polluted forever. Compounds GB-006/GB-003 severity.",
    "recommendation": "Add DELETE endpoints scoped to admin/manajer. Add 'void' status for transactions with Kredit BukuKas entry. Add DELETE for /inventory/purchases/{id} or /inventory/batches/{id}.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-020",
    "title": "BukuKas credit/refund flow tidak ada — hanya Debit dari /pay",
    "severity": "LOW",
    "category": "Missing Endpoint / Business Logic",
    "cwe": "n/a",
    "endpoint": "n/a (missing)",
    "file": "apps/api/app/Services/TransaksiService.php:87-92",
    "evidence": "TransaksiService::pay only creates BukuKas with tipe='Debit'. No endpoint creates Kredit. Report 'finance' therefore will always show monotonically increasing saldo without refund capability.",
    "repro_steps": [
      "POST several /pay — finance report shows only Debit rows, no Kredit"
    ],
    "impact": "Cannot refund a customer. If a payment is later voided, the cash ledger is wrong.",
    "recommendation": "Add `POST /api/transactions/{id}/refund` (Manajer only) that creates a Kredit BukuKas and sets transaksi.status='Refund' or similar. Audit log mandatory.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-021",
    "title": "Manajer punya akses ke /pay — separation of duties question",
    "severity": "INFO",
    "category": "Authorization Spec Gap",
    "cwe": "n/a",
    "endpoint": "POST /api/transactions/pay",
    "file": "apps/api/routes/api.php:18-19",
    "evidence": "Middleware: 'role:Kasir,Manajer'. Manajer can both create AND audit transactions. No second-pair-of-eyes enforcement.",
    "repro_steps": [
      "Login as Manajer → POST /api/transactions/pay works (same as Kasir)"
    ],
    "impact": "Internal fraud risk: Manajer can self-approve a sale. Most retail systems forbid the role that does audit from also doing the action.",
    "recommendation": "Per PRD: document intent. If separation required, drop Manajer from /pay middleware; add a separate 'Supervisor override' endpoint.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-022",
    "title": "firstOut calculation break when duplicate batch codes exist with same expiry",
    "severity": "LOW",
    "category": "Logic Bug",
    "cwe": "CWE-840",
    "endpoint": "GET /api/bootstrap",
    "file": "apps/api/app/Http/Controllers/Api/BootstrapController.php:127-134",
    "evidence": "After addPurchase stored duplicate 'XSS-1' + 'XSS-1' with same expiry, bootstrap returns both. firstOut=true only on the FIRST one. But decrementStock uses `lockForUpdate + order by expiry + order by id` so it's deterministic. The 'firstOut' flag is a UI hint — it's correct. But: with 2 identical expiry+name, the UI shows BOTH, and only the first is 'firstOut' visually. After partial depletion, the second may become the next in line but is not flagged.",
    "repro_steps": [
      "POST /inventory/purchases twice with same kode_batch='XSS-1' and same expiry",
      "Bootstrap shows two entries, one firstOut:true"
    ],
    "impact": "Misleading UI; no constraint preventing duplicates. Add a UNIQUE(produk_id, kode_batch).",
    "recommendation": "Add DB unique index on (produk_id, kode_batch). Re-evaluate firstOut logic to re-compute after each decrement, not just from $i===0.",
    "status": "PARTIAL"
  },
  {
    "id": "GB-023",
    "title": "Bootstrap /api/patients/{id} GET tidak ada — FE harus re-bootstrap untuk refresh 1 patient",
    "severity": "INFO",
    "category": "Missing Endpoint / UX Gap",
    "cwe": "n/a",
    "endpoint": "missing GET /api/patients/{id}",
    "file": "apps/api/routes/api.php (no such route)",
    "evidence": "Routes file has no GET /api/patients/{id} or /api/patients/{id}/treatments. After addTreatment, FE has no way to re-fetch the patient's record without /bootstrap (5MB+ payload).",
    "repro_steps": [
      "POST /api/patients/1/treatments {terapis:'X', judul:'Y', catatan:'Z'}",
      "No endpoint to GET back just that patient"
    ],
    "impact": "UX/perf gap. Compounds GB-018.",
    "recommendation": "Add `GET /api/patients/{id}` and `GET /api/patients/{id}/treatments` returning only the requested patient with treatments/photos.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-024",
    "title": "APP_DEBUG=true — full Laravel stack trace returned on any unhandled exception",
    "severity": "HIGH",
    "category": "Information Disclosure (Environment)",
    "cwe": "CWE-209 + CWE-489 (Active Debug Code)",
    "endpoint": "All error responses",
    "file": ".env (APP_DEBUG=true)",
    "evidence": "GB-001, GB-002 reproduced. Each 500 response includes: `exception` class, `file` path, `line` number, full `trace` array with absolute Windows paths. Verified: 3 different 500s all return full traces (login no-level, /pay insufficient stock, /treatments on non-existent patient).",
    "repro_steps": [
      "Any unhandled exception in dev: e.g. POST /login without level/role"
    ],
    "impact": "Pre-prod: leaks filesystem, Laravel version, internal class names. In production this would be a major CVE.",
    "recommendation": "Set APP_DEBUG=false in any environment reachable by the user. Wrap domain exceptions (RuntimeException from TransaksiService) in a custom exception caught by handler → 422 with safe message.",
    "status": "VERIFIED"
  },
  {
    "id": "GB-025",
    "title": "Telegram /reminder returns 200 even when not sent (silent failure)",
    "severity": "LOW",
    "category": "Error Handling",
    "cwe": "CWE-754",
    "endpoint": "POST /api/telegram/reminder",
    "file": "apps/api/app/Http/Controllers/Api/TelegramController.php",
    "evidence": "POST /telegram/reminder {pasien_id:1, when:'besok'} as Kasir returns 200 {sent:false, to:'...', patient:'Alya Maharani'}. The `sent:false` is success in HTTP terms — caller cannot distinguish 'sent OK' from 'integration disabled' from 'API down'.",
    "repro_steps": [
      "POST /api/telegram/reminder valid body as Kasir",
      "Response 200 with sent:false"
    ],
    "impact": "FE will show 'reminder sent' to user even though it wasn't. Patient doesn't get the message.",
    "recommendation": "When sent=false, return 200 but include a `warning` field, or return 503 with retry guidance. FE should show a toast based on sent flag.",
    "status": "VERIFIED"
  }
]
