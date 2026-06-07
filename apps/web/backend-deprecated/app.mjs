import express from "express";
import { randomUUID } from "node:crypto";
import { tmpdir } from "node:os";
import { fileURLToPath } from "node:url";
import { openDatabase } from "./database.mjs";
import { createFinancePdf, createWorkbook } from "./reporting.mjs";
import { storeClinicalPhoto } from "./storage.mjs";

const defaultStorageRoot = process.env.VERCEL
  ? `${tmpdir()}/simkk-storage`
  : fileURLToPath(new URL("../storage", import.meta.url));

export async function createApp(options = {}) {
  const database = await openDatabase(options.dbPath);
  const storageRoot = options.storageRoot ?? defaultStorageRoot;
  const app = express();

  app.use(express.json({ limit: "6mb" }));

  const asyncRoute = (handler) => async (request, response, next) => {
    try {
      await handler(request, response, next);
    } catch (error) {
      next(error);
    }
  };

  const auth = (request, response, next) => {
    const token = request.headers.authorization?.replace(/^Bearer\s+/i, "");
    const user = token ? database.findSessionUser(token) : null;
    if (!user) {
      response.status(401).json({ message: "Sesi tidak valid." });
      return;
    }
    request.user = user;
    next();
  };
  const requireRole = (...roles) => (request, response, next) => {
    if (!roles.includes(request.user.role)) {
      response.status(403).json({ message: "Role tidak memiliki akses ke aksi ini." });
      return;
    }
    next();
  };

  app.get("/api/health", (_request, response) => {
    response.json({ ok: true });
  });

  app.post("/api/login", asyncRoute(async (request, response) => {
    const { username, password, role } = request.body ?? {};
    const user = database.verifyLogin(username, password, role);
    if (!user) {
      response.status(401).json({ message: "Username, password, atau role salah." });
      return;
    }
    const token = `simkk_${randomUUID().replaceAll("-", "")}`;
    await database.createSession(user.id, token);
    response.json({ token, user });
  }));

  app.get("/api/bootstrap", auth, (_request, response) => {
    response.json({
      users: database.getUsers(),
      patients: database.getPatients(),
      services: database.getServices(),
      therapists: database.getTherapists(),
      transactions: database.getTransactions(),
      inventory: database.getInventory(),
      reports: database.getReports(),
    });
  });

  app.post("/api/transactions/pay", auth, requireRole("Kasir", "Manajer"), asyncRoute(async (request, response) => {
    const result = await database.createPaidTransaction(request.body);
    response.status(201).json(result);
  }));

  app.post("/api/patients/:patientId/treatments", auth, requireRole("Terapis", "Manajer"), asyncRoute(async (request, response) => {
    const result = await database.addTreatment(Number(request.params.patientId), request.body);
    response.status(201).json(result);
  }));

  app.post("/api/patients/:patientId/photos", auth, requireRole("Terapis", "Manajer"), asyncRoute(async (request, response) => {
    const patient = database.getPatients().find((item) => item.id === Number(request.params.patientId));
    if (!patient) {
      response.status(404).json({ message: "Pasien tidak ditemukan." });
      return;
    }
    const objectRef = await storeClinicalPhoto(storageRoot, patient.recordId, request.body);
    const result = await database.addPhoto(patient.id, { label: request.body.label, objectRef });
    response.status(201).json(result);
  }));

  app.post("/api/inventory/purchases", auth, requireRole("Gudang", "Manajer"), asyncRoute(async (request, response) => {
    const result = await database.addPurchase(request.body);
    response.status(201).json(result);
  }));

  app.get("/api/reports/:reportId/export", auth, requireRole("Manajer"), asyncRoute(async (request, response) => {
    const report = database.getReports().find((item) => item.id === request.params.reportId);
    if (!report) {
      response.status(404).json({ message: "Report tidak ditemukan." });
      return;
    }
    if (report.output === "PDF") {
      const pdf = await createFinancePdf(report);
      response.setHeader("content-type", "application/pdf");
      response.setHeader("content-disposition", `attachment; filename="${report.id}.pdf"`);
      response.send(pdf);
      return;
    }
    const workbook = await createWorkbook(report);
    response.setHeader("content-type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    response.setHeader("content-disposition", `attachment; filename="${report.id}.xlsx"`);
    response.send(workbook);
  }));

  app.use((error, _request, response, _next) => {
    const status = error.status || 500;
    response.status(status).json({ message: error.message || "Terjadi kesalahan server." });
  });

  app.locals.database = database;
  return app;
}
