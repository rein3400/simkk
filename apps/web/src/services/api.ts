import type { InventoryProduct, Patient, ReportPreview, Role, ServiceItem, Therapist, Transaction, TreatmentNote, User } from "../types/domain";

// API base URL — for cross-origin (SPA on IP, API on subdomain) use full URL.
// In production, set VITE_API_BASE=https://api.sim-kk.example.id
// For local testing without DNS, add to /etc/hosts: 43.133.142.74 api.sim-kk.example.id
const API_BASE = (import.meta.env.VITE_API_BASE as string) || "";

const apiUrl = (path: string) => `${API_BASE}${path}`;

export interface AppData {
  users: User[];
  patients: Patient[];
  services: ServiceItem[];
  therapists: Therapist[];
  transactions: Transaction[];
  inventory: InventoryProduct[];
  reports: ReportPreview[];
}

export interface LoginPayload {
  username: string;
  password: string;
  role: Role;
}

export interface LoginResult {
  token: string;
  user: User;
}

const parseJson = async <T>(response: Response): Promise<T> => {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? "Request gagal.");
  return body as T;
};

const authHeaders = (token: string) => ({
  authorization: `Bearer ${token}`,
  "content-type": "application/json",
});

const TOKEN_STORAGE_KEY = "simkk_token";

/** Read the auth token from localStorage (used outside the App shell). */
export function readStoredToken(): string {
  try {
    return localStorage.getItem(TOKEN_STORAGE_KEY) ?? "";
  } catch {
    return "";
  }
}

const authedFetch = (token: string, path: string, init: Omit<RequestInit, "headers"> = {}) => {
  const headers: Record<string, string> = {
    "content-type": "application/json",
  };
  if ((init as RequestInit).headers) {
    Object.assign(headers, (init as RequestInit).headers as Record<string, string>);
  }
  if (token) headers.authorization = `Bearer ${token}`;
  return fetch(apiUrl(path), { ...init, headers });
};

export async function login(payload: LoginPayload): Promise<LoginResult> {
  const response = await fetch(apiUrl("/api/login"), {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify(payload),
  });
  return parseJson<LoginResult>(response);
}

export async function getBootstrap(token: string): Promise<AppData> {
  const response = await fetch(apiUrl("/api/bootstrap"), {
    headers: { authorization: `Bearer ${token}` },
  });
  return parseJson<AppData>(response);
}

export async function payTransaction(token: string, payload: {
  patientId: number;
  therapistId: number;
  items: { serviceId: number; qty: number }[];
  paymentMethod?: string;
  discount?: number;
}) {
  // Backend expects Indonesian snake_case fields
  const body = {
    pasien_id: payload.patientId,
    terapis_id: payload.therapistId,
    items: payload.items,
    discount: payload.discount ?? 0,
    metode_bayar: payload.paymentMethod ?? "Tunai",
  };
  const response = await fetch(apiUrl("/api/transactions/pay"), {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });
  return parseJson<{ transaction: Transaction; receipt: { id: string; paymentMethod?: string; discount?: number }; cashLedger: { amount: number } }>(response);
}

export async function addTreatment(token: string, patientId: number, payload: {
  therapist: string;
  title: string;
  notes: string;
}) {
  const response = await fetch(apiUrl(`/api/patients/${patientId}/treatments`), {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  return parseJson(response);
}

export async function addClinicalPhoto(token: string, patientId: number, payload: {
  label: "Before" | "After";
  filename: string;
  content: string;
}) {
  const response = await fetch(apiUrl(`/api/patients/${patientId}/photos`), {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  return parseJson(response);
}

export async function addPurchase(token: string, payload: {
  productId: number;
  supplier: string;
  batchCode: string;
  qty: number;
  hpp: number;
  expiry: string;
}) {
  const response = await fetch(apiUrl("/api/inventory/purchases"), {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  return parseJson<InventoryProduct>(response);
}

export async function downloadReport(token: string, reportId: ReportPreview["id"]): Promise<Blob> {
  const response = await fetch(apiUrl(`/api/reports/${reportId}/export`), {
    headers: { authorization: `Bearer ${token}` },
  });
  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new Error(body.message ?? "Export gagal.");
  }
  return response.blob();
}

/* ====================================================================
 * Admin · Layanan CRUD
 * ==================================================================== */

export interface LayananRecord {
  id: number;
  nama: string;
  kategori: string;
  durasi: string;
  harga: number;
  komisi_rate: number;
  komisi_persen?: number;
  stok_produk_id?: number | null;
  dampak_stok?: string;
  created_at?: string;
  updated_at?: string;
}

export async function listLayanan(token: string): Promise<LayananRecord[]> {
  const response = await authedFetch(token, "/api/admin/layanan", { method: "GET"  });
  return parseJson<LayananRecord[]>(response);
}

export async function createLayanan(token: string, payload: Omit<LayananRecord, "id" | "created_at" | "updated_at">): Promise<LayananRecord> {
  const response = await authedFetch(token, "/api/admin/layanan", { method: "POST",
    body: JSON.stringify(payload) });
  return parseJson<LayananRecord>(response);
}

export async function getLayanan(token: string, id: number): Promise<LayananRecord> {
  const response = await authedFetch(token, `/api/admin/layanan/${id}`, { method: "GET"  });
  return parseJson<LayananRecord>(response);
}

export async function updateLayanan(token: string, id: number, payload: Partial<Omit<LayananRecord, "id" | "created_at" | "updated_at">>): Promise<LayananRecord> {
  const response = await authedFetch(token, `/api/admin/layanan/${id}`, { method: "PUT",
    body: JSON.stringify(payload) });
  return parseJson<LayananRecord>(response);
}

export async function deleteLayanan(token: string, id: number): Promise<{ deleted: boolean; id: number }> {
  const response = await authedFetch(token, `/api/admin/layanan/${id}`, { method: "DELETE"  });
  return parseJson(response);
}

/* ====================================================================
 * Admin · Produk CRUD
 * ==================================================================== */

export interface ProdukRecord {
  id: number;
  nama: string;
  kategori: string;
  total_stok: number;
  status: "Aman" | "Menipis" | "Prioritas";
  created_at?: string;
  updated_at?: string;
}

export async function listProduk(token: string): Promise<ProdukRecord[]> {
  const response = await authedFetch(token, "/api/admin/produk", { method: "GET"  });
  return parseJson<ProdukRecord[]>(response);
}

export async function createProduk(token: string, payload: Omit<ProdukRecord, "id" | "created_at" | "updated_at">): Promise<ProdukRecord> {
  const response = await authedFetch(token, "/api/admin/produk", { method: "POST",
    body: JSON.stringify(payload) });
  return parseJson<ProdukRecord>(response);
}

export async function getProduk(token: string, id: number): Promise<ProdukRecord> {
  const response = await authedFetch(token, `/api/admin/produk/${id}`, { method: "GET"  });
  return parseJson<ProdukRecord>(response);
}

export async function updateProduk(token: string, id: number, payload: Partial<Omit<ProdukRecord, "id" | "created_at" | "updated_at">>): Promise<ProdukRecord> {
  const response = await authedFetch(token, `/api/admin/produk/${id}`, { method: "PUT",
    body: JSON.stringify(payload) });
  return parseJson<ProdukRecord>(response);
}

export async function deleteProduk(token: string, id: number): Promise<{ deleted: boolean; id: number }> {
  const response = await authedFetch(token, `/api/admin/produk/${id}`, { method: "DELETE"  });
  return parseJson(response);
}

/* ====================================================================
 * Admin · Users CRUD
 * ==================================================================== */

export interface UserRecord {
  id: number;
  username: string;
  nama_lengkap: string;
  level: Role;
  shift: string;
  created_at?: string;
  updated_at?: string;
}

export interface UserCreatePayload {
  username: string;
  password: string;
  nama_lengkap: string;
  level: Role;
  shift: string;
}

export interface UserUpdatePayload {
  username?: string;
  password?: string;
  nama_lengkap?: string;
  level?: Role;
  shift?: string;
}

export async function listUsers(token: string): Promise<UserRecord[]> {
  const response = await authedFetch(token, "/api/admin/users", { method: "GET"  });
  return parseJson<UserRecord[]>(response);
}

export async function createUser(token: string, payload: UserCreatePayload): Promise<UserRecord> {
  const response = await authedFetch(token, "/api/admin/users", { method: "POST",
    body: JSON.stringify(payload) });
  return parseJson<UserRecord>(response);
}

export async function getUser(token: string, id: number): Promise<UserRecord> {
  const response = await authedFetch(token, `/api/admin/users/${id}`, { method: "GET"  });
  return parseJson<UserRecord>(response);
}

export async function updateUser(token: string, id: number, payload: UserUpdatePayload): Promise<UserRecord> {
  const response = await authedFetch(token, `/api/admin/users/${id}`, { method: "PUT",
    body: JSON.stringify(payload) });
  return parseJson<UserRecord>(response);
}

export async function deleteUser(token: string, id: number): Promise<{ deleted: boolean; id: number }> {
  const response = await authedFetch(token, `/api/admin/users/${id}`, { method: "DELETE"  });
  return parseJson(response);
}

/* ====================================================================
 * Audit logs
 * ==================================================================== */

export interface AuditLogEntry {
  id: number;
  user_id: number | null;
  user_name?: string;
  username?: string;
  action: string;
  entitas?: string | null;
  entitas_id?: number | null;
  payload?: Record<string, unknown> | null;
  created_at: string;
}

export async function getAuditLogs(token: string, params: { limit?: number; action?: string; user_id?: number } = {}): Promise<AuditLogEntry[]> {
  const search = new URLSearchParams();
  if (params.limit) search.set("limit", String(params.limit));
  if (params.action) search.set("action", params.action);
  if (params.user_id) search.set("user_id", String(params.user_id));
  const qs = search.toString();
  const response = await authedFetch(token, `/api/audit-logs${qs ? `?${qs}` : ""}`, { method: "GET" });
  // Backend returns {count, rows: [{id, username, nama_lengkap, action, ...}]}
  // Normalise to AuditLogEntry[] expected by the view.
  const body = await parseJson<{ count?: number; rows?: any[] }>(response);
  const rows = Array.isArray(body?.rows) ? body.rows : [];
  return rows.map((row: any) => ({
    id: row.id,
    user_id: row.user_id ?? null,
    user_name: row.nama_lengkap ?? row.username ?? undefined,
    username: row.username,
    action: row.action,
    entitas: row.entitas,
    entitas_id: row.entitas_id,
    payload: row.payload,
    created_at: row.created_at,
  }));
}

/* ====================================================================
 * Dashboard
 * ==================================================================== */

export interface DashboardResponse {
  revenue_today: number;
  revenue_yesterday: number;
  revenue_growth_pct: number;
  transactions_today: number;
  pending_closings: number;
  low_stock_count: number;
  top_therapists: { nama: string; tindakan: number; komisi: number }[];
  top_services: { nama: string; count: number }[];
  last_7_days_revenue: { date: string; total: number }[];
  date: string;
}

export async function getDashboard(token: string): Promise<DashboardResponse> {
  const response = await authedFetch(token, "/api/dashboard", { method: "GET"  });
  return parseJson<DashboardResponse>(response);
}

/* ====================================================================
 * Daily reports
 * ==================================================================== */

export interface DailyReportStatus {
  tanggal: string;
  status: "pending" | "submitted" | "approved" | "empty" | null;
  total_penjualan: number;
  total_komisi: number;
  transaction_count: number;
  closing_id: number | null;
}

export async function getDailyReportStatus(token: string, tanggal: string): Promise<DailyReportStatus> {
  const response = await authedFetch(token, `/api/daily-reports/status?tanggal=${encodeURIComponent(tanggal)}`, { method: "GET" });
  return parseJson<DailyReportStatus>(response);
}

export async function submitDailyReport(token: string, tanggal: string): Promise<DailyReportStatus> {
  const response = await authedFetch(token, `/api/daily-reports/${encodeURIComponent(tanggal)}/submit`, { method: "POST",
    body: JSON.stringify({ }),
  });
  return parseJson<DailyReportStatus>(response);
}

export async function approveDailyReport(token: string, closingId: number): Promise<DailyReportStatus> {
  const response = await authedFetch(token, `/api/daily-reports/closings/${closingId}/approve`, { method: "POST",
    body: JSON.stringify({ }),
  });
  return parseJson<DailyReportStatus>(response);
}

export async function exportDailyReport(token: string, tanggal: string): Promise<Blob> {
  const response = await fetch(apiUrl(`/api/daily-reports/${encodeURIComponent(tanggal)}/export`), {
    headers: { authorization: `Bearer ${token}` },
  });
  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new Error(body.message ?? "Export PDF gagal.");
  }
  return response.blob();
}

/* ====================================================================
 * Backup trigger
 * ==================================================================== */

export async function triggerBackup(token: string): Promise<{ ok: boolean; filename?: string; message?: string; size?: number }> {
  const response = await authedFetch(token, "/api/backup/trigger", { method: "POST",
    body: JSON.stringify({ }),
  });
  return parseJson(response);
}

/* ====================================================================
 * Treatments / Photos / Transactions destroy
 * ==================================================================== */

export async function updateTreatment(token: string, patientId: number, treatmentId: number, payload: {
  therapist: string;
  title: string;
  notes: string;
}): Promise<TreatmentNote> {
  const response = await authedFetch(
    token,
    `/api/patients/${patientId}/treatments/${treatmentId}`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
  );
  return parseJson<TreatmentNote>(response);
}

export async function deleteTreatment(token: string, patientId: number, treatmentId: number): Promise<{ deleted: boolean }> {
  const response = await authedFetch(
    token,
    `/api/patients/${patientId}/treatments/${treatmentId}`,
    { method: "DELETE" },
  );
  return parseJson(response);
}

export async function deleteClinicalPhoto(token: string, patientId: number, photoId: string | number): Promise<{ deleted: boolean }> {
  const response = await authedFetch(
    token,
    `/api/patients/${patientId}/photos/${photoId}`,
    { method: "DELETE" },
  );
  return parseJson(response);
}

export async function deleteTransaction(token: string, transactionId: string | number): Promise<{ deleted: boolean }> {
  const response = await authedFetch(
    token,
    `/api/transactions/${transactionId}`,
    { method: "DELETE" },
  );
  return parseJson(response);
}

export async function deletePurchase(token: string, batchId: number): Promise<{ deleted: boolean }> {
  const response = await authedFetch(
    token,
    `/api/inventory/purchases/${batchId}`,
    { method: "DELETE" },
  );
  return parseJson(response);
}
