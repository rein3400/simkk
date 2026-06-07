import { after, before, describe, it } from "node:test";
import assert from "node:assert/strict";
import { mkdtemp, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { createApp } from "../app.mjs";

const requestJson = async (baseUrl, path, options = {}) => {
  const response = await fetch(`${baseUrl}${path}`, {
    ...options,
    headers: {
      "content-type": "application/json",
      ...(options.token ? { authorization: `Bearer ${options.token}` } : {}),
      ...(options.headers ?? {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const contentType = response.headers.get("content-type") ?? "";
  const body = contentType.includes("application/json")
    ? await response.json()
    : Buffer.from(await response.arrayBuffer());

  return { response, body };
};

describe("SIM-KK real backend", () => {
  let tempDir;
  let server;
  let baseUrl;
  const credentials = {
    Kasir: "kasir",
    Terapis: "terapis",
    Gudang: "gudang",
    Manajer: "manajer",
  };

  before(async () => {
    tempDir = await mkdtemp(join(tmpdir(), "simkk-api-"));
    const app = await createApp({
      dbPath: join(tempDir, "simkk.sqlite"),
      storageRoot: join(tempDir, "storage"),
    });
    await new Promise((resolve) => {
      server = app.listen(0, "127.0.0.1", resolve);
    });
    baseUrl = `http://127.0.0.1:${server.address().port}`;
  });

  after(async () => {
    await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
    await rm(tempDir, { force: true, recursive: true });
  });

  const loginAs = async (role) => {
    const login = await requestJson(baseUrl, "/api/login", {
      method: "POST",
      body: { username: credentials[role], password: "simkk-2026", role },
    });
    assert.equal(login.response.status, 200);
    assert.equal(login.body.user.role, role);
    assert.match(login.body.token, /^simkk_/);
    return login.body.token;
  };

  it("logs in against stored users and returns bootstrap data", async () => {
    const token = await loginAs("Kasir");

    const bootstrap = await requestJson(baseUrl, "/api/bootstrap", { token });
    assert.equal(bootstrap.response.status, 200);
    assert.ok(bootstrap.body.patients.length >= 2);
    assert.ok(bootstrap.body.services.length >= 4);
    assert.ok(bootstrap.body.inventory.length >= 2);
  });

  it("finalizes paid POS transactions with commission snapshot, cash ledger, and FIFO stock mutation", async () => {
    const token = await loginAs("Kasir");
    const before = await requestJson(baseUrl, "/api/bootstrap", { token });
    const sunscreen = before.body.inventory.find((item) => item.name === "Daily Sunscreen SPF50");
    const firstBatchBefore = sunscreen.batches[0].qty;
    const patient = before.body.patients[0];
    const service = before.body.services.find((item) => item.name === "Acne Calm Facial");
    const product = before.body.services.find((item) => item.name === "Daily Sunscreen SPF50");
    const therapist = before.body.therapists.find((item) => item.name === "Rani Wulandari");

    const paid = await requestJson(baseUrl, "/api/transactions/pay", {
      method: "POST",
      token,
      body: {
        patientId: patient.id,
        therapistId: therapist.id,
        items: [
          { serviceId: service.id, qty: 1 },
          { serviceId: product.id, qty: 1 },
        ],
        discount: 25000,
        paymentMethod: "QRIS",
      },
    });

    assert.equal(paid.response.status, 201);
    assert.equal(paid.body.transaction.status, "Lunas");
    assert.equal(paid.body.transaction.subtotal, service.price + product.price);
    assert.equal(paid.body.transaction.discount, 25000);
    assert.equal(paid.body.transaction.paymentMethod, "QRIS");
    assert.equal(paid.body.transaction.total, service.price + product.price - 25000);
    assert.ok(paid.body.transaction.commission > 0);
    assert.equal(paid.body.cashLedger.type, "Debit");
    assert.equal(paid.body.cashLedger.amount, service.price + product.price - 25000);
    assert.equal(paid.body.receipt.paymentMethod, "QRIS");
    assert.equal(paid.body.receipt.discount, 25000);
    assert.ok(paid.body.receipt.id.startsWith("RCPT-"));

    const afterPay = await requestJson(baseUrl, "/api/bootstrap", { token });
    const sunscreenAfter = afterPay.body.inventory.find((item) => item.name === "Daily Sunscreen SPF50");
    assert.equal(sunscreenAfter.batches[0].qty, firstBatchBefore - 1);
  });

  it("persists medical notes and local clinical photo object references", async () => {
    const token = await loginAs("Terapis");
    const bootstrap = await requestJson(baseUrl, "/api/bootstrap", { token });
    const patient = bootstrap.body.patients[0];

    const note = await requestJson(baseUrl, `/api/patients/${patient.id}/treatments`, {
      method: "POST",
      token,
      body: {
        therapist: "Rani Wulandari",
        title: "Barrier follow-up",
        notes: "Kemerahan turun, lanjutkan calming toner malam.",
      },
    });

    assert.equal(note.response.status, 201);
    assert.equal(note.body.title, "Barrier follow-up");

    const photo = await requestJson(baseUrl, `/api/patients/${patient.id}/photos`, {
      method: "POST",
      token,
      body: {
        label: "After",
        filename: "after-followup.txt",
        content: "clinical-photo-placeholder",
      },
    });

    assert.equal(photo.response.status, 201);
    assert.match(photo.body.objectRef, /^local:\/\/clinical\//);

    const afterSave = await requestJson(baseUrl, "/api/bootstrap", { token });
    const refreshed = afterSave.body.patients.find((item) => item.id === patient.id);
    assert.ok(refreshed.treatments.some((item) => item.title === "Barrier follow-up"));
    assert.ok(refreshed.photos.some((item) => item.objectRef === photo.body.objectRef));
  });

  it("records supplier purchase batches and exports real report files", async () => {
    const gudangToken = await loginAs("Gudang");
    const managerToken = await loginAs("Manajer");
    const purchase = await requestJson(baseUrl, "/api/inventory/purchases", {
      method: "POST",
      token: gudangToken,
      body: {
        productId: 1,
        supplier: "PT Dermalab",
        batchCode: "BS-REAL-0526",
        qty: 4,
        hpp: 101000,
        expiry: "2027-05-25",
      },
    });

    assert.equal(purchase.response.status, 201);
    assert.ok(purchase.body.batches.some((batch) => batch.code === "BS-REAL-0526"));

    const pdf = await requestJson(baseUrl, "/api/reports/finance/export", { token: managerToken });
    assert.equal(pdf.response.status, 200);
    assert.equal(pdf.response.headers.get("content-type"), "application/pdf");
    assert.equal(pdf.body.subarray(0, 4).toString(), "%PDF");

    const xlsx = await requestJson(baseUrl, "/api/reports/commission/export", { token: managerToken });
    assert.equal(xlsx.response.status, 200);
    assert.match(xlsx.response.headers.get("content-type"), /spreadsheetml/);
    assert.equal(xlsx.body.subarray(0, 2).toString("hex"), "504b");
  });

  it("rejects actions outside the active role scope", async () => {
    const kasirToken = await loginAs("Kasir");
    const terapisToken = await loginAs("Terapis");
    const bootstrap = await requestJson(baseUrl, "/api/bootstrap", { token: kasirToken });
    const patient = bootstrap.body.patients[0];
    const service = bootstrap.body.services[0];
    const therapist = bootstrap.body.therapists[0];

    const medicalWrite = await requestJson(baseUrl, `/api/patients/${patient.id}/treatments`, {
      method: "POST",
      token: kasirToken,
      body: { therapist: "Rani Wulandari", title: "Should fail", notes: "Kasir tidak boleh tulis rekam medis." },
    });
    assert.equal(medicalWrite.response.status, 403);

    const posPay = await requestJson(baseUrl, "/api/transactions/pay", {
      method: "POST",
      token: terapisToken,
      body: {
        patientId: patient.id,
        therapistId: therapist.id,
        items: [{ serviceId: service.id, qty: 1 }],
      },
    });
    assert.equal(posPay.response.status, 403);

    const exportReport = await requestJson(baseUrl, "/api/reports/finance/export", { token: kasirToken });
    assert.equal(exportReport.response.status, 403);
  });
});
