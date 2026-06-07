import initSqlJs from "sql.js";
import { existsSync } from "node:fs";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { hashPassword, seed } from "./seed.mjs";

const wasmPath = fileURLToPath(new URL("../node_modules/sql.js/dist/sql-wasm.wasm", import.meta.url));

const defaultDbPath = process.env.VERCEL
  ? `${tmpdir()}/simkk.sqlite`
  : fileURLToPath(new URL("../data/simkk.sqlite", import.meta.url));

export const rupiah = (value) => new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
}).format(Number(value));

const todayLabel = () => new Intl.DateTimeFormat("id-ID", {
  day: "2-digit",
  month: "short",
}).format(new Date()).replace(".", "");

export async function openDatabase(dbPath = defaultDbPath) {
  const SQL = await initSqlJs({ locateFile: () => wasmPath });
  await mkdir(dirname(dbPath), { recursive: true });
  const db = existsSync(dbPath)
    ? new SQL.Database(await readFile(dbPath))
    : new SQL.Database();

  const persist = async () => {
    await writeFile(dbPath, Buffer.from(db.export()));
  };

  const run = (sql, params = []) => {
    const statement = db.prepare(sql);
    try {
      statement.bind(params);
      statement.step();
    } finally {
      statement.free();
    }
  };

  const all = (sql, params = []) => {
    const statement = db.prepare(sql);
    const rows = [];
    try {
      statement.bind(params);
      while (statement.step()) rows.push(statement.getAsObject());
      return rows;
    } finally {
      statement.free();
    }
  };

  const one = (sql, params = []) => all(sql, params)[0] ?? null;

  const migrate = () => {
    db.run(`
      PRAGMA foreign_keys = ON;
      CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL,
        shift TEXT NOT NULL
      );
      CREATE TABLE IF NOT EXISTS sessions (
        token TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id)
      );
      CREATE TABLE IF NOT EXISTS patients (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        age INTEGER NOT NULL,
        phone TEXT NOT NULL,
        record_id TEXT NOT NULL UNIQUE,
        concern TEXT NOT NULL,
        last_visit TEXT NOT NULL,
        risk_note TEXT NOT NULL
      );
      CREATE TABLE IF NOT EXISTS treatment_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        therapist TEXT NOT NULL,
        title TEXT NOT NULL,
        notes TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(patient_id) REFERENCES patients(id)
      );
      CREATE TABLE IF NOT EXISTS clinical_photos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        label TEXT NOT NULL,
        date TEXT NOT NULL,
        object_ref TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(patient_id) REFERENCES patients(id)
      );
      CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        duration TEXT NOT NULL,
        price INTEGER NOT NULL,
        commission_rate REAL NOT NULL,
        stock_product_id INTEGER,
        stock_impact TEXT
      );
      CREATE TABLE IF NOT EXISTS therapists (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        specialty TEXT NOT NULL,
        status TEXT NOT NULL
      );
      CREATE TABLE IF NOT EXISTS transactions (
        id TEXT PRIMARY KEY,
        patient_id INTEGER NOT NULL,
        therapist_id INTEGER,
        status TEXT NOT NULL,
        subtotal INTEGER NOT NULL DEFAULT 0,
        discount INTEGER NOT NULL DEFAULT 0,
        payment_method TEXT NOT NULL DEFAULT 'Tunai',
        total INTEGER NOT NULL,
        commission INTEGER NOT NULL,
        time TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(patient_id) REFERENCES patients(id),
        FOREIGN KEY(therapist_id) REFERENCES therapists(id)
      );
      CREATE TABLE IF NOT EXISTS transaction_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id TEXT NOT NULL,
        service_id INTEGER NOT NULL,
        qty INTEGER NOT NULL,
        price INTEGER NOT NULL,
        commission INTEGER NOT NULL,
        FOREIGN KEY(transaction_id) REFERENCES transactions(id),
        FOREIGN KEY(service_id) REFERENCES services(id)
      );
      CREATE TABLE IF NOT EXISTS cash_ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id TEXT NOT NULL,
        type TEXT NOT NULL,
        amount INTEGER NOT NULL,
        description TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(transaction_id) REFERENCES transactions(id)
      );
      CREATE TABLE IF NOT EXISTS inventory_products (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        category TEXT NOT NULL
      );
      CREATE TABLE IF NOT EXISTS inventory_batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        code TEXT NOT NULL,
        qty INTEGER NOT NULL,
        hpp INTEGER NOT NULL,
        expiry TEXT NOT NULL,
        supplier TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(product_id) REFERENCES inventory_products(id)
      );
      CREATE TABLE IF NOT EXISTS supplier_purchases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        batch_code TEXT NOT NULL,
        qty INTEGER NOT NULL,
        hpp INTEGER NOT NULL,
        supplier TEXT NOT NULL,
        expiry TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(product_id) REFERENCES inventory_products(id)
      );
    `);
    const transactionColumns = all("PRAGMA table_info(transactions)").map((column) => column.name);
    if (!transactionColumns.includes("subtotal")) db.run("ALTER TABLE transactions ADD COLUMN subtotal INTEGER NOT NULL DEFAULT 0");
    if (!transactionColumns.includes("discount")) db.run("ALTER TABLE transactions ADD COLUMN discount INTEGER NOT NULL DEFAULT 0");
    if (!transactionColumns.includes("payment_method")) db.run("ALTER TABLE transactions ADD COLUMN payment_method TEXT NOT NULL DEFAULT 'Tunai'");
  };

  const seedIfNeeded = () => {
    if (one("SELECT id FROM users LIMIT 1")) return;

    for (const user of seed.users) {
      run("INSERT INTO users (id, username, password_hash, name, role, shift) VALUES (?, ?, ?, ?, ?, ?)", [
        user.id,
        user.username,
        hashPassword(user.password),
        user.name,
        user.role,
        user.shift,
      ]);
    }
    for (const patient of seed.patients) {
      run("INSERT INTO patients (id, name, age, phone, record_id, concern, last_visit, risk_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
        patient.id,
        patient.name,
        patient.age,
        patient.phone,
        patient.recordId,
        patient.concern,
        patient.lastVisit,
        patient.riskNote,
      ]);
    }
    for (const note of seed.treatments) {
      run("INSERT INTO treatment_notes (patient_id, date, therapist, title, notes, created_at) VALUES (?, ?, ?, ?, ?, ?)", [
        note.patientId,
        note.date,
        note.therapist,
        note.title,
        note.notes,
        new Date().toISOString(),
      ]);
    }
    for (const photo of seed.photos) {
      run("INSERT INTO clinical_photos (patient_id, label, date, object_ref, created_at) VALUES (?, ?, ?, ?, ?)", [
        photo.patientId,
        photo.label,
        photo.date,
        photo.objectRef,
        new Date().toISOString(),
      ]);
    }
    for (const service of seed.services) {
      run("INSERT INTO services (id, name, category, duration, price, commission_rate, stock_product_id, stock_impact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
        service.id,
        service.name,
        service.category,
        service.duration,
        service.price,
        service.commissionRate,
        service.stockProductId ?? null,
        service.stockImpact ?? null,
      ]);
    }
    for (const therapist of seed.therapists) {
      run("INSERT INTO therapists (id, name, specialty, status) VALUES (?, ?, ?, ?)", [
        therapist.id,
        therapist.name,
        therapist.specialty,
        therapist.status,
      ]);
    }
    for (const transaction of seed.transactions) {
      run("INSERT INTO transactions (id, patient_id, therapist_id, status, subtotal, discount, payment_method, total, commission, time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
        transaction.id,
        transaction.patientId,
        transaction.therapistId,
        transaction.status,
        transaction.total,
        0,
        "Tunai",
        transaction.total,
        transaction.commission,
        transaction.time,
        new Date().toISOString(),
      ]);
      if (transaction.status === "Lunas") {
        run("INSERT INTO cash_ledger (transaction_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)", [
          transaction.id,
          "Debit",
          transaction.total,
          `Pembayaran ${transaction.id}`,
          new Date().toISOString(),
        ]);
      }
    }
    for (const product of seed.inventoryProducts) {
      run("INSERT INTO inventory_products (id, name, category) VALUES (?, ?, ?)", [
        product.id,
        product.name,
        product.category,
      ]);
    }
    for (const batch of seed.batches) {
      run("INSERT INTO inventory_batches (product_id, code, qty, hpp, expiry, supplier, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)", [
        batch.productId,
        batch.code,
        batch.qty,
        batch.hpp,
        batch.expiry,
        batch.supplier,
        new Date().toISOString(),
      ]);
    }
  };

  migrate();
  seedIfNeeded();
  await persist();

  const toUser = (row) => ({
    id: Number(row.id),
    username: row.username,
    name: row.name,
    role: row.role,
    shift: row.shift,
  });

  const toService = (row) => ({
    id: Number(row.id),
    name: row.name,
    category: row.category,
    duration: row.duration,
    price: Number(row.price),
    commissionRate: Number(row.commission_rate),
    stockProductId: row.stock_product_id === null ? undefined : Number(row.stock_product_id),
    stockImpact: row.stock_impact ?? undefined,
  });

  const getUsers = () => all("SELECT id, username, name, role, shift FROM users ORDER BY id").map(toUser);

  const getPatients = () => all("SELECT * FROM patients ORDER BY id").map((patient) => ({
    id: Number(patient.id),
    name: patient.name,
    age: Number(patient.age),
    phone: patient.phone,
    recordId: patient.record_id,
    concern: patient.concern,
    lastVisit: patient.last_visit,
    riskNote: patient.risk_note,
    treatments: all("SELECT * FROM treatment_notes WHERE patient_id = ? ORDER BY id", [patient.id]).map((note) => ({
      id: Number(note.id),
      date: note.date,
      therapist: note.therapist,
      title: note.title,
      notes: note.notes,
    })),
    photos: all("SELECT * FROM clinical_photos WHERE patient_id = ? ORDER BY id", [patient.id]).map((photo) => ({
      id: String(photo.id),
      label: photo.label,
      date: photo.date,
      objectRef: photo.object_ref,
    })),
  }));

  const getTherapists = () => all("SELECT * FROM therapists ORDER BY id").map((row) => ({
    id: Number(row.id),
    name: row.name,
    specialty: row.specialty,
    status: row.status,
  }));

  const getServices = () => all("SELECT * FROM services ORDER BY id").map(toService);

  const getTransactions = () => all(`
    SELECT t.*, p.name AS patient, th.name AS therapist
    FROM transactions t
    JOIN patients p ON p.id = t.patient_id
    LEFT JOIN therapists th ON th.id = t.therapist_id
    ORDER BY t.created_at DESC, t.id DESC
  `).map((row) => ({
    id: row.id,
    patient: row.patient,
    therapist: row.therapist ?? "-",
    status: row.status,
    subtotal: Number(row.subtotal || row.total),
    discount: Number(row.discount || 0),
    paymentMethod: row.payment_method || "Tunai",
    total: Number(row.total),
    commission: Number(row.commission),
    time: row.time,
  }));

  const statusForBatches = (total, firstExpiry) => {
    if (firstExpiry !== "Reusable" && firstExpiry <= "2026-07-31") return "Prioritas";
    if (total <= 12) return "Menipis";
    return "Aman";
  };

  const getInventory = () => all("SELECT * FROM inventory_products ORDER BY id").map((product) => {
    const batches = all(`
      SELECT * FROM inventory_batches
      WHERE product_id = ? AND qty > 0
      ORDER BY CASE WHEN expiry = 'Reusable' THEN '9999-12-31' ELSE expiry END ASC, id ASC
    `, [product.id]);
    const totalStock = batches.reduce((sum, batch) => sum + Number(batch.qty), 0);
    return {
      id: Number(product.id),
      name: product.name,
      category: product.category,
      totalStock,
      status: statusForBatches(totalStock, batches[0]?.expiry ?? "9999-12-31"),
      batches: batches.map((batch, index) => ({
        code: batch.code,
        qty: Number(batch.qty),
        hpp: Number(batch.hpp),
        expiry: batch.expiry,
        supplier: batch.supplier,
        firstOut: index === 0,
      })),
    };
  });

  const getReports = () => {
    const ledgerRows = all("SELECT * FROM cash_ledger ORDER BY id");
    let saldo = 0;
    const financeRows = ledgerRows.map((row) => {
      const debit = row.type === "Debit" ? Number(row.amount) : 0;
      const kredit = row.type === "Kredit" ? Number(row.amount) : 0;
      saldo += debit - kredit;
      return { id: row.transaction_id, debit: rupiah(debit), kredit: rupiah(kredit), saldo: rupiah(saldo) };
    });
    const stockRows = getInventory().flatMap((product) => product.batches.slice(0, 1).map((batch) => ({
      produk: product.name,
      stok: product.totalStock,
      batch: batch.code,
      hpp: rupiah(batch.hpp),
    })));
    const commissionRows = all(`
      SELECT th.id AS idPegawai, th.name AS pegawai, COUNT(t.id) AS tindakan, SUM(t.commission) AS komisi
      FROM therapists th
      LEFT JOIN transactions t ON t.therapist_id = th.id AND t.status = 'Lunas'
      GROUP BY th.id, th.name
      ORDER BY th.id
    `).map((row) => {
      const komisi = Number(row.komisi ?? 0);
      const gajiPokok = 2500000;
      return {
        idPegawai: `TRP-${String(row.idPegawai).padStart(3, "0")}`,
        pegawai: row.pegawai,
        tindakan: Number(row.tindakan),
        komisi: rupiah(komisi),
        gajiPokok: rupiah(gajiPokok),
        grandTotal: rupiah(gajiPokok + komisi),
      };
    });

    return [
      { id: "finance", title: "Laporan Arus Kas", output: "PDF", period: "Mei 2026", rows: financeRows },
      { id: "stock", title: "Laporan Stok FIFO", output: "XLSX", period: "Mei 2026", rows: stockRows },
      { id: "commission", title: "Komisi Terapis", output: "XLSX", period: "Mei 2026", rows: commissionRows },
    ];
  };

  const findSessionUser = (token) => {
    const row = one(`
      SELECT u.id, u.username, u.name, u.role, u.shift
      FROM sessions s
      JOIN users u ON u.id = s.user_id
      WHERE s.token = ?
    `, [token]);
    return row ? toUser(row) : null;
  };

  const createSession = async (userId, token) => {
    run("INSERT INTO sessions (token, user_id, created_at) VALUES (?, ?, ?)", [token, userId, new Date().toISOString()]);
    await persist();
  };

  const verifyLogin = (username, password, role) => {
    const row = one("SELECT * FROM users WHERE username = ? AND role = ?", [username, role]);
    if (!row) return null;
    return row.password_hash === hashPassword(password) ? toUser(row) : null;
  };

  const decrementStock = (productId, qty) => {
    let remaining = qty;
    const batches = all(`
      SELECT * FROM inventory_batches
      WHERE product_id = ? AND qty > 0
      ORDER BY CASE WHEN expiry = 'Reusable' THEN '9999-12-31' ELSE expiry END ASC, id ASC
    `, [productId]);
    const available = batches.reduce((sum, batch) => sum + Number(batch.qty), 0);
    if (available < qty) throw Object.assign(new Error("Stok FIFO tidak cukup."), { status: 409 });
    for (const batch of batches) {
      if (remaining <= 0) break;
      const taken = Math.min(Number(batch.qty), remaining);
      run("UPDATE inventory_batches SET qty = ? WHERE id = ?", [Number(batch.qty) - taken, batch.id]);
      remaining -= taken;
    }
  };

  const createPaidTransaction = async ({ patientId, therapistId, items, discount = 0, paymentMethod = "Tunai" }) => {
    if (!patientId || !therapistId || !Array.isArray(items) || items.length === 0) {
      throw Object.assign(new Error("Pasien, terapis, dan item wajib diisi."), { status: 400 });
    }
    const patient = one("SELECT * FROM patients WHERE id = ?", [patientId]);
    const therapist = one("SELECT * FROM therapists WHERE id = ?", [therapistId]);
    if (!patient || !therapist) throw Object.assign(new Error("Pasien atau terapis tidak ditemukan."), { status: 404 });

    const resolvedItems = items.map((item) => {
      const service = one("SELECT * FROM services WHERE id = ?", [item.serviceId]);
      if (!service) throw Object.assign(new Error("Item layanan tidak ditemukan."), { status: 404 });
      const qty = Math.max(1, Number(item.qty ?? 1));
      return { service, qty };
    });

    for (const item of resolvedItems) {
      if (item.service.stock_product_id !== null) decrementStock(Number(item.service.stock_product_id), item.qty);
    }

    const subtotal = resolvedItems.reduce((sum, item) => sum + Number(item.service.price) * item.qty, 0);
    const discountValue = Math.min(subtotal, Math.max(0, Number(discount || 0)));
    const total = subtotal - discountValue;
    const method = String(paymentMethod || "Tunai").slice(0, 32);
    const commission = Math.round(resolvedItems.reduce((sum, item) => (
      sum + Number(item.service.price) * Number(item.service.commission_rate) * item.qty
    ), 0));
    const count = Number(one("SELECT COUNT(*) AS count FROM transactions").count) + 1;
    const id = `TRX-${new Date().toISOString().slice(2, 10).replaceAll("-", "")}-${String(count).padStart(3, "0")}`;
    const receiptId = `RCPT-${id}`;
    const time = new Intl.DateTimeFormat("id-ID", { hour: "2-digit", minute: "2-digit", hour12: false }).format(new Date()).replace(".", ":");
    const createdAt = new Date().toISOString();

    run("INSERT INTO transactions (id, patient_id, therapist_id, status, subtotal, discount, payment_method, total, commission, time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
      id,
      patientId,
      therapistId,
      "Lunas",
      subtotal,
      discountValue,
      method,
      total,
      commission,
      time,
      createdAt,
    ]);
    for (const item of resolvedItems) {
      run("INSERT INTO transaction_items (transaction_id, service_id, qty, price, commission) VALUES (?, ?, ?, ?, ?)", [
        id,
        item.service.id,
        item.qty,
        item.service.price,
        Math.round(Number(item.service.price) * Number(item.service.commission_rate) * item.qty),
      ]);
    }
    run("INSERT INTO cash_ledger (transaction_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)", [
      id,
      "Debit",
      total,
      `Pembayaran ${receiptId} via ${method}${discountValue ? `, diskon ${rupiah(discountValue)}` : ""}`,
      createdAt,
    ]);
    await persist();

    return {
      transaction: {
        id,
        patient: patient.name,
        therapist: therapist.name,
        status: "Lunas",
        subtotal,
        discount: discountValue,
        paymentMethod: method,
        total,
        commission,
        time,
      },
      receipt: { id: receiptId, transactionId: id, subtotal, discount: discountValue, paymentMethod: method, total },
      cashLedger: { type: "Debit", amount: total, transactionId: id },
    };
  };

  const addTreatment = async (patientId, input) => {
    const patient = one("SELECT id FROM patients WHERE id = ?", [patientId]);
    if (!patient) throw Object.assign(new Error("Pasien tidak ditemukan."), { status: 404 });
    const date = todayLabel();
    run("INSERT INTO treatment_notes (patient_id, date, therapist, title, notes, created_at) VALUES (?, ?, ?, ?, ?, ?)", [
      patientId,
      date,
      input.therapist,
      input.title,
      input.notes,
      new Date().toISOString(),
    ]);
    await persist();
    return {
      id: Number(one("SELECT last_insert_rowid() AS id").id),
      date,
      therapist: input.therapist,
      title: input.title,
      notes: input.notes,
    };
  };

  const addPhoto = async (patientId, { label, objectRef }) => {
    const patient = one("SELECT id FROM patients WHERE id = ?", [patientId]);
    if (!patient) throw Object.assign(new Error("Pasien tidak ditemukan."), { status: 404 });
    const date = todayLabel();
    run("INSERT INTO clinical_photos (patient_id, label, date, object_ref, created_at) VALUES (?, ?, ?, ?, ?)", [
      patientId,
      label,
      date,
      objectRef,
      new Date().toISOString(),
    ]);
    await persist();
    return { id: String(one("SELECT last_insert_rowid() AS id").id), label, date, objectRef };
  };

  const addPurchase = async ({ productId, supplier, batchCode, qty, hpp, expiry }) => {
    const product = one("SELECT * FROM inventory_products WHERE id = ?", [productId]);
    if (!product) throw Object.assign(new Error("Produk tidak ditemukan."), { status: 404 });
    const createdAt = new Date().toISOString();
    run("INSERT INTO supplier_purchases (product_id, batch_code, qty, hpp, supplier, expiry, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)", [
      productId,
      batchCode,
      qty,
      hpp,
      supplier,
      expiry,
      createdAt,
    ]);
    run("INSERT INTO inventory_batches (product_id, code, qty, hpp, expiry, supplier, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)", [
      productId,
      batchCode,
      qty,
      hpp,
      expiry,
      supplier,
      createdAt,
    ]);
    await persist();
    return getInventory().find((item) => item.id === Number(productId));
  };

  return {
    all,
    one,
    run,
    persist,
    close: () => db.close(),
    getUsers,
    getPatients,
    getServices,
    getTherapists,
    getTransactions,
    getInventory,
    getReports,
    verifyLogin,
    createSession,
    findSessionUser,
    createPaidTransaction,
    addTreatment,
    addPhoto,
    addPurchase,
  };
}
