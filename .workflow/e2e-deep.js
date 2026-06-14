#!/usr/bin/env node
// Comprehensive E2E Test Suite for SIM-KK
// Run: node .workflow/e2e-deep.js

const BASE = "http://127.0.0.1:8000";
let PASS = 0, FAIL = 0, TOTAL = 0;

async function api(desc, expectedCode, method, url, opts = {}) {
    TOTAL++;
    try {
        const init = { method, headers: { "Content-Type": "application/json", ...opts.headers } };
        if (opts.body && typeof opts.body === "object") init.body = JSON.stringify(opts.body);
        else if (opts.body && typeof opts.body === "string") init.body = opts.body;
        const r = await fetch(url, init);
        const ct = r.headers.get("content-type") || "";
        let data;
        if (ct.includes("json")) data = await r.json();
        else data = await r.text();
        if (r.status === expectedCode) {
            console.log(`✅ PASS: ${desc} (HTTP ${r.status})`);
            PASS++;
        } else {
            const snippet = typeof data === "string" ? data.slice(0, 200) : JSON.stringify(data).slice(0, 200);
            console.log(`❌ FAIL: ${desc} (expected ${expectedCode}, got ${r.status}) ${snippet}`);
            FAIL++;
        }
        return { status: r.status, data };
    } catch (e) {
        console.log(`❌ FAIL: ${desc} (error: ${e.message})`);
        FAIL++;
        return { status: 0, data: null };
    }
}

function assert(desc, ok) {
    if (ok) { console.log(`   ✅ ${desc}`); PASS++; } else { console.log(`   ❌ ${desc}`); FAIL++; }
    TOTAL++;
}

async function main() {
    console.log("=== SIM-KK E2E Test Suite ===");
    console.log(`Server: ${BASE}\n`);

    // ── 1. AUTH ──────────────────────────────────────────────────
    console.log("━━━ 1. AUTHENTICATION ━━━");
    // Login 4 roles first (separate from negative tests to avoid rate limit)
    const { data: kLogin } = await api("Login Kasir", 200, "POST", `${BASE}/api/login`,
        { body: { username: "kasir", password: "simkk-2026", level: "Kasir" } });
    const { data: tLogin } = await api("Login Terapis", 200, "POST", `${BASE}/api/login`,
        { body: { username: "terapis", password: "simkk-2026", level: "Terapis" } });
    const { data: gLogin } = await api("Login Gudang", 200, "POST", `${BASE}/api/login`,
        { body: { username: "gudang", password: "simkk-2026", level: "Gudang" } });
    const { data: mLogin } = await api("Login Manajer", 200, "POST", `${BASE}/api/login`,
        { body: { username: "manajer", password: "simkk-2026", level: "Manajer" } });

    const KT = kLogin?.token, TT = tLogin?.token, GT = gLogin?.token, MT = mLogin?.token;

    // Negative tests AFTER valid logins (separate rate limit budget)
    await api("Login bad password", 401, "POST", `${BASE}/api/login`,
        { body: { username: "kasir", password: "wrong", level: "Kasir" } });
    await api("Login missing role", 422, "POST", `${BASE}/api/login`,
        { body: { username: "kasir", password: "simkk-2026" } });
    await api("Login empty body", 422, "POST", `${BASE}/api/login`, { body: {} });

    // Logout test (uses fresh token, doesn't kill KT for subsequent tests)
    const { data: kLogoutR } = await api("Login Kasir (for logout test)", 200, "POST", `${BASE}/api/login`,
        { body: { username: "kasir", password: "simkk-2026", level: "Kasir" } });
    if (kLogoutR?.token) {
        await api("Logout Kasir", 200, "POST", `${BASE}/api/logout`,
            { headers: { Authorization: `Bearer ${kLogoutR.token}` } });
    }

    // ── 2. BOOTSTRAP (role-scoped) ──────────────────────────────
    console.log("\n━━━ 2. BOOTSTRAP (ROLE-SCOPED) ━━━");
    const { data: bsM } = await api("Bootstrap Manajer", 200, "GET", `${BASE}/api/bootstrap`,
        { headers: { Authorization: `Bearer ${MT}` } });
    if (bsM) {
        const p = bsM.patients?.length ?? 0, tx = bsM.transactions?.length ?? 0, s = bsM.services?.length ?? 0;
        const inv = bsM.inventory?.length ?? 0, rpt = bsM.reports?.length ?? 0;
        console.log(`   patients=${p}, transactions=${tx}, services=${s}, inventory=${inv}, reports=${rpt}`);
        assert("Manajer sees patient treatments/photos", p > 0 && bsM.patients[0]?.treatments !== undefined);
    }

    const { data: bsK } = await api("Bootstrap Kasir", 200, "GET", `${BASE}/api/bootstrap`,
        { headers: { Authorization: `Bearer ${KT}` } });
    if (bsK) {
        console.log(`   patients=${bsK.patients?.length}, transactions=${bsK.transactions?.length}`);
        assert("Kasir does NOT see patient treatments", bsK.patients?.[0]?.treatments === undefined);
        assert("Kasir does NOT see inventory", bsK.inventory?.length === 0);
        assert("Kasir does NOT see reports", bsK.reports?.length === 0);
    }

    const { data: bsG } = await api("Bootstrap Gudang", 200, "GET", `${BASE}/api/bootstrap`,
        { headers: { Authorization: `Bearer ${GT}` } });
    if (bsG) {
        assert("Gudang sees no patients", bsG.patients?.length === 0);
        assert("Gudang sees no services", bsG.services?.length === 0);
        assert("Gudang sees no transactions", bsG.transactions?.length === 0);
        assert("Gudang sees inventory", bsG.inventory?.length > 0);
    }

    // ── 3. POS / TRANSACTIONS ───────────────────────────────────
    console.log("\n━━━ 3. POS / TRANSACTIONS ━━━");
    const idempKey = `e2e-${Date.now()}`;
    const { data: payR } = await api("Pay transaction", 201, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${KT}`, "Idempotency-Key": idempKey },
        body: { pasien_id: 1, terapis_id: 1, items: [{ serviceId: 1, qty: 2 }], diskon: 0, metode_bayar: "Tunai" }
    });
    const trxId = payR?.transaction?.id;
    const trxRowId = payR?.transaction?.rowId;
    console.log(`   Transaction: ${trxId} (rowId=${trxRowId})`);
    console.log(`   Transaction: ${trxId}`);

    // Idempotency replay
    const { data: payR2 } = await api("Idempotency replay (same key)", 201, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${KT}`, "Idempotency-Key": idempKey },
        body: { pasien_id: 1, terapis_id: 1, items: [{ serviceId: 1, qty: 2 }], diskon: 0, metode_bayar: "Tunai" }
    });
    assert("Idempotency returns same TRX", payR2?.transaction?.id === trxId);

    await api("Pay missing items", 422, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${KT}` }, body: { pasien_id: 1, terapis_id: 1 }
    });
    await api("Pay empty items", 422, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${KT}` }, body: { pasien_id: 1, terapis_id: 1, items: [] }
    });
    await api("Pay qty > 100", 422, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${KT}` }, body: { pasien_id: 1, terapis_id: 1, items: [{ serviceId: 1, qty: 101 }] }
    });
    await api("Pay invalid metode_bayar", 422, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${KT}` }, body: { pasien_id: 1, terapis_id: 1, items: [{ serviceId: 1, qty: 1 }], metode_bayar: "Bitcoin" }
    });
    await api("Pay denied for Gudang", 403, "POST", `${BASE}/api/transactions/pay`, {
        headers: { Authorization: `Bearer ${GT}` }, body: { pasien_id: 1, terapis_id: 1, items: [{ serviceId: 1, qty: 1 }] }
    });

    // ── 4. VOID ─────────────────────────────────────────────────
    console.log("\n━━━ 4. VOID / DELETE TRANSACTION ━━━");
    if (trxRowId) {
        await api("Void transaction (Manajer)", 200, "DELETE", `${BASE}/api/transactions/${trxRowId}`,
            { headers: { Authorization: `Bearer ${MT}` } });
        await api("Void denied for Kasir", 403, "DELETE", `${BASE}/api/transactions/${trxRowId}`,
            { headers: { Authorization: `Bearer ${KT}` } });
        await api("Void denied for Terapis", 403, "DELETE", `${BASE}/api/transactions/${trxRowId}`,
            { headers: { Authorization: `Bearer ${TT}` } });
    }

    // ── 5. MEDICAL RECORDS ──────────────────────────────────────
    console.log("\n━━━ 5. MEDICAL RECORDS ━━━");
    const { data: treatR } = await api("Add treatment (Terapis)", 201, "POST", `${BASE}/api/patients/1/treatments`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { judul: "E2E Test Treatment", catatan: "Testing E2E flow" }
    });
    const treatId = treatR?.id;
    console.log(`   Treatment ID: ${treatId}, Date: ${treatR?.date}`);
    if (treatR?.date) assert("Date format Y-m-d", /^\d{4}-\d{2}-\d{2}$/.test(treatR.date));

    if (treatId) {
        await api("Update treatment", 200, "PUT", `${BASE}/api/patients/1/treatments/${treatId}`, {
            headers: { Authorization: `Bearer ${TT}` }, body: { judul: "Updated E2E", catatan: "Updated" }
        });
        await api("Delete treatment", 200, "DELETE", `${BASE}/api/patients/1/treatments/${treatId}`,
            { headers: { Authorization: `Bearer ${TT}` } });
    }
    await api("Treatment denied for Gudang", 403, "POST", `${BASE}/api/patients/1/treatments`, {
        headers: { Authorization: `Bearer ${GT}` }, body: { judul: "X", catatan: "Y" }
    });
    await api("Treatment missing judul", 422, "POST", `${BASE}/api/patients/1/treatments`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { catatan: "no title" }
    });
    await api("Treatment catatan too long", 422, "POST", `${BASE}/api/patients/1/treatments`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { judul: "X", catatan: "A".repeat(6000) }
    });
    await api("Treatment terapis field prohibited", 422, "POST", `${BASE}/api/patients/1/treatments`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { judul: "X", catatan: "Y", terapis: "Fake Name" }
    });

    // ── 6. PHOTOS ───────────────────────────────────────────────
    console.log("\n━━━ 6. PHOTO UPLOAD ━━━");
    // Minimal valid 1x1 RGBA PNG (transparent) — verified with Node.js CRC
    const pngB64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGNgAAIAAAUAAXpeqz8AAAAASUVORK5CYII=";

    const { data: photoR } = await api("Upload valid photo", 201, "POST", `${BASE}/api/patients/1/photos`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { label: "Before", filename: "e2e.png", content: pngB64 }
    });
    console.log(`   Photo ID: ${photoR?.id}`);

    await api("Photo .php rejected", 422, "POST", `${BASE}/api/patients/1/photos`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { label: "Before", filename: "shell.php", content: pngB64 }
    });
    await api("Photo .exe rejected", 422, "POST", `${BASE}/api/patients/1/photos`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { label: "Before", filename: "shell.exe", content: pngB64 }
    });
    await api("Photo .svg rejected", 422, "POST", `${BASE}/api/patients/1/photos`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { label: "Before", filename: "evil.svg", content: pngB64 }
    });
    await api("Photo content too large", 422, "POST", `${BASE}/api/patients/1/photos`, {
        headers: { Authorization: `Bearer ${TT}` }, body: { label: "Before", filename: "big.png", content: "A".repeat(20000) }
    });

    // ── 7. INVENTORY ────────────────────────────────────────────
    console.log("\n━━━ 7. INVENTORY ━━━");
    await api("Add purchase (Gudang)", 201, "POST", `${BASE}/api/inventory/purchases`, {
        headers: { Authorization: `Bearer ${GT}` },
        body: { produk_id: 1, supplier: "PT E2E", kode_batch: `E2E-${Date.now()}`, qty: 10, hpp: 50000, kadaluarsa: "2027-12-31" }
    });
    await api("Batch traversal rejected", 422, "POST", `${BASE}/api/inventory/purchases`, {
        headers: { Authorization: `Bearer ${GT}` },
        body: { produk_id: 1, supplier: "PT X", kode_batch: "../../etc/passwd", qty: 10, hpp: 50000 }
    });
    await api("Batch spaces rejected", 422, "POST", `${BASE}/api/inventory/purchases`, {
        headers: { Authorization: `Bearer ${GT}` },
        body: { produk_id: 1, supplier: "PT X", kode_batch: "has spaces", qty: 10, hpp: 50000 }
    });
    await api("Batch .. rejected", 422, "POST", `${BASE}/api/inventory/purchases`, {
        headers: { Authorization: `Bearer ${GT}` },
        body: { produk_id: 1, supplier: "PT X", kode_batch: "a..b", qty: 10, hpp: 50000 }
    });
    await api("Batch with dots OK", 201, "POST", `${BASE}/api/inventory/purchases`, {
        headers: { Authorization: `Bearer ${GT}` },
        body: { produk_id: 1, supplier: "PT X", kode_batch: `BATCH.OK-${Date.now()}`, qty: 5, hpp: 30000 }
    });
    await api("Purchase denied for Kasir", 403, "POST", `${BASE}/api/inventory/purchases`, {
        headers: { Authorization: `Bearer ${KT}` },
        body: { produk_id: 1, supplier: "PT X", kode_batch: "X", qty: 1, hpp: 100 }
    });

    // ── 8. REPORTS ──────────────────────────────────────────────
    console.log("\n━━━ 8. REPORTS ━━━");
    await api("Export finance PDF (Manajer)", 200, "GET", `${BASE}/api/reports/finance/export`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Export stock XLSX (Manajer)", 200, "GET", `${BASE}/api/reports/stock/export`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Export commission XLSX (Manajer)", 200, "GET", `${BASE}/api/reports/commission/export`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Report denied for Kasir", 403, "GET", `${BASE}/api/reports/finance/export`,
        { headers: { Authorization: `Bearer ${KT}` } });

    // ── 9. DAILY REPORTS ────────────────────────────────────────
    console.log("\n━━━ 9. DAILY REPORTS ━━━");
    const today = new Date().toISOString().slice(0, 10);
    const { data: drStatus } = await api("Daily report status", 200, "GET",
        `${BASE}/api/daily-reports/status?tanggal=${today}`, { headers: { Authorization: `Bearer ${KT}` } });
    console.log(`   status=${drStatus?.status}, transactions=${drStatus?.transaction_count}, closing_id=${drStatus?.closing_id}`);

    // Submit only if not already submitted/approved (idempotent test runs)
    if (drStatus?.status === "pending" || drStatus?.status === "empty" || drStatus?.status === null) {
        await api("Submit daily report (Kasir)", 200, "POST", `${BASE}/api/daily-reports/${today}/submit`,
            { headers: { Authorization: `Bearer ${KT}` }, body: {} });
    } else {
        console.log(`   ℹ️  Submit skipped (already ${drStatus?.status})`);
    }

    const { data: drAfter } = await api("Daily status after submit", 200, "GET",
        `${BASE}/api/daily-reports/status?tanggal=${today}`, { headers: { Authorization: `Bearer ${KT}` } });
    if (drAfter?.status === "submitted" && drAfter?.closing_id) {
        await api("Approve daily report (Manajer)", 200, "POST",
            `${BASE}/api/daily-reports/closings/${drAfter.closing_id}/approve`,
            { headers: { Authorization: `Bearer ${MT}` }, body: {} });
    } else {
        console.log(`   ℹ️  Approve skipped (status=${drAfter?.status})`);
    }
    await api("Export daily report PDF", 200, "GET", `${BASE}/api/daily-reports/${today}/export`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Daily report invalid date", 422, "GET", `${BASE}/api/daily-reports/status?tanggal=bad-date`,
        { headers: { Authorization: `Bearer ${KT}` } });

    // ── 10. ADMIN CRUD ──────────────────────────────────────────
    console.log("\n━━━ 10. ADMIN CRUD (MANAJER ONLY) ━━━");
    const { data: layList } = await api("GET layanan (Manajer)", 200, "GET", `${BASE}/api/admin/layanan`,
        { headers: { Authorization: `Bearer ${MT}` } });
    if (Array.isArray(layList)) console.log(`   ${layList.length} layanan found`);

    await api("GET layanan denied (Kasir)", 403, "GET", `${BASE}/api/admin/layanan`,
        { headers: { Authorization: `Bearer ${KT}` } });
    await api("GET layanan denied (Terapis)", 403, "GET", `${BASE}/api/admin/layanan`,
        { headers: { Authorization: `Bearer ${TT}` } });
    await api("GET layanan denied (Gudang)", 403, "GET", `${BASE}/api/admin/layanan`,
        { headers: { Authorization: `Bearer ${GT}` } });
    await api("GET produk (Manajer)", 200, "GET", `${BASE}/api/admin/produk`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("GET users (Manajer)", 200, "GET", `${BASE}/api/admin/users`,
        { headers: { Authorization: `Bearer ${MT}` } });

    // ── 11. OTHER ENDPOINTS ─────────────────────────────────────
    console.log("\n━━━ 11. OTHER ENDPOINTS ━━━");
    await api("Dashboard (Manajer)", 200, "GET", `${BASE}/api/dashboard`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Dashboard denied (Kasir)", 403, "GET", `${BASE}/api/dashboard`,
        { headers: { Authorization: `Bearer ${KT}` } });
    await api("Audit logs (Manajer)", 200, "GET", `${BASE}/api/audit-logs`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Audit logs denied (Kasir)", 403, "GET", `${BASE}/api/audit-logs`,
        { headers: { Authorization: `Bearer ${KT}` } });
    await api("Search (Manajer)", 200, "GET", `${BASE}/api/search?q=test`,
        { headers: { Authorization: `Bearer ${MT}` } });
    await api("Inventory movements (Gudang)", 200, "GET", `${BASE}/api/inventory-movements`,
        { headers: { Authorization: `Bearer ${GT}` } });

    // ── 12. SECURITY ────────────────────────────────────────────
    console.log("\n━━━ 12. SECURITY ━━━");
    await api("Bootstrap no token", 401, "GET", `${BASE}/api/bootstrap`);
    await api("Pay no token", 401, "POST", `${BASE}/api/transactions/pay`, { body: {} });
    await api("Bootstrap bad token", 401, "GET", `${BASE}/api/bootstrap`,
        { headers: { Authorization: "Bearer invalid-token-12345" } });

    // CORS check (fetch-based, need to read raw)
    try {
        const corsR = await fetch(`${BASE}/api/transactions/pay`, { method: "OPTIONS", headers: { Origin: "http://evil.com" } });
        const acao = corsR.headers.get("access-control-allow-origin");
        assert("CORS not wildcard", acao !== "*");
    } catch { console.log("   ⚠️ CORS check skipped (fetch OPTIONS)"); }

    // ── SUMMARY ─────────────────────────────────────────────────
    console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("SUMMARY");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log(`Total:  ${TOTAL}`);
    console.log(`Passed: ${PASS}`);
    console.log(`Failed: ${FAIL}`);
    console.log("");
    if (FAIL === 0) console.log("🎉 ALL TESTS PASSED");
    else console.log(`⚠️  ${FAIL} TESTS FAILED`);
    process.exit(FAIL > 0 ? 1 : 0);
}

main().catch(e => { console.error(e); process.exit(1); });
