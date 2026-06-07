import type { Role, ViewKey } from "../types/domain";

export interface RoleProfile {
  defaultView: ViewKey;
  allowedViews: ViewKey[];
  scope: string;
  loginHint: string;
}

export const roleProfiles: Record<Role, RoleProfile> = {
  Kasir: {
    defaultView: "pos",
    allowedViews: ["pos", "daily-report"],
    scope: "POS, pembayaran, receipt, dan komisi transaksi.",
    loginHint: "Masuk langsung ke POS untuk menutup transaksi pasien.",
  },
  Terapis: {
    defaultView: "medical",
    allowedViews: ["medical"],
    scope: "Rekam medis, catatan tindakan, dan foto klinis.",
    loginHint: "Masuk ke timeline pasien, catatan tindakan, dan foto before/after.",
  },
  Gudang: {
    defaultView: "inventory",
    allowedViews: ["inventory"],
    scope: "Stok, batch FIFO, HPP, dan barang masuk.",
    loginHint: "Masuk ke stok, batch FIFO, HPP, dan pembelian supplier.",
  },
  Manajer: {
    defaultView: "dashboard",
    allowedViews: [
      "dashboard",
      "reports",
      "pos",
      "medical",
      "inventory",
      "admin-layanan",
      "admin-produk",
      "admin-users",
      "audit-log",
      "daily-report",
    ],
    scope: "Dashboard, audit operasional, dan akses lintas modul.",
    loginHint: "Masuk ke dashboard, laporan, audit, dan modul admin klinik.",
  },
  Admin: {
    defaultView: "dashboard",
    allowedViews: [
      "dashboard",
      "reports",
      "pos",
      "medical",
      "inventory",
      "admin-layanan",
      "admin-produk",
      "admin-users",
      "audit-log",
      "daily-report",
    ],
    scope: "Sama dengan Manajer, sesuai PRD 2.3.1 Level=Admin.",
    loginHint: "Akses penuh lintas modul, seperti Manajer.",
  },
};

export const canOpenView = (role: Role, view: ViewKey) => roleProfiles[role].allowedViews.includes(view);
