import type { InventoryProduct, Patient, ReportPreview, Role, ServiceItem, Therapist, Transaction, User } from "../types/domain";

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
  const response = await fetch(apiUrl("/api/transactions/pay"), {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
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
