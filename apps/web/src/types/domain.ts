export type Role = "Kasir" | "Terapis" | "Gudang" | "Manajer" | "Admin";

export type ViewKey =
  | "pos"
  | "medical"
  | "inventory"
  | "reports"
  | "admin-layanan"
  | "admin-produk"
  | "admin-users"
  | "audit-log"
  | "dashboard"
  | "daily-report";

export interface User {
  id: number;
  username?: string;
  /** Backend uses `nama_lengkap`; keep `name` as alias for UI compat. */
  nama_lengkap?: string;
  name: string;
  role: Role;
  shift: string;
}

export interface Patient {
  id: number;
  /** Backend `nama_pasien`; keep `name` for UI. */
  nama_pasien?: string;
  /** PRD field, was missing in prototype. */
  alamat?: string;
  name: string;
  age: number;
  phone: string;
  recordId: string;
  concern: string;
  lastVisit: string;
  riskNote: string;
  treatments: TreatmentNote[];
  photos: ClinicalPhoto[];
  // Per revisi R8 — collapsible session cards grouped by date.
  sessions?: TreatmentSession[];
}

export interface TreatmentSession {
  date: string;
  treatments: TreatmentNote[];
  photos: ClinicalPhoto[];
  note_excerpt: string | null;
}

export interface TreatmentNote {
  id?: number;
  date: string;
  therapist: string;
  title: string;
  notes: string;
}

export interface ClinicalPhoto {
  id: string;
  label: "Before" | "After";
  date: string;
  objectRef: string;
  url?: string | null;
}

export interface ServiceItem {
  id: number;
  name: string;
  category: "Treatment" | "Produk" | "Paket";
  duration: string;
  price: number;
  commissionRate: number;
  stockProductId?: number;
  stockImpact?: string;
}

export interface Therapist {
  id: number;
  name: string;
  specialty: string;
  status: "Tersedia" | "Treatment" | "Istirahat";
}

export interface Transaction {
  id: string;
  patient: string;
  therapist: string;
  status: "Draft" | "Lunas" | "Menunggu";
  subtotal?: number;
  discount?: number;
  paymentMethod?: string;
  total: number;
  commission: number;
  time: string;
}

export interface InventoryBatch {
  id?: number;
  code: string;
  qty: number;
  hpp: number;
  expiry: string;
  supplier: string;
  firstOut: boolean;
}

export interface InventoryProduct {
  id: number;
  name: string;
  category: string;
  totalStock: number;
  status: "Aman" | "Pending" | "Habis";
  batches: InventoryBatch[];
}

export interface ReportPreview {
  id: "finance" | "stock" | "commission";
  title: string;
  output: "PDF" | "XLSX";
  period: string;
  rows: Record<string, string | number>[];
}
