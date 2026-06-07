<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import AppShell from "./components/AppShell.vue";
import LoginView from "./views/LoginView.vue";
import PosView from "./views/PosView.vue";
import MedicalRecordView from "./views/MedicalRecordView.vue";
import InventoryView from "./views/InventoryView.vue";
import ReportsView from "./views/ReportsView.vue";
import AdminLayananView from "./views/AdminLayananView.vue";
import AdminProdukView from "./views/AdminProdukView.vue";
import AdminUsersView from "./views/AdminUsersView.vue";
import AuditLogView from "./views/AuditLogView.vue";
import DashboardView from "./views/DashboardView.vue";
import DailyReportView from "./views/DailyReportView.vue";
import { getBootstrap, login, type AppData, type LoginPayload } from "./services/api";
import type { Role, User, ViewKey } from "./types/domain";
import { canOpenView, roleProfiles } from "./utils/access";

const REFRESH_INTERVAL_MS = 30_000;

const loggedIn = ref(false);
const currentRole = ref<Role>("Kasir");
const activeView = ref<ViewKey>("pos");
const token = ref("");
const authUser = ref<User | null>(null);
const searchQuery = ref("");
const loading = ref(false);
const loginError = ref("");
const refreshing = ref(false);
const lastUpdated = ref<Date | null>(null);
const realtimeEnabled = ref(false);
let refreshTimer: number | null = null;
const appData = ref<AppData>({
  users: [],
  patients: [],
  services: [],
  therapists: [],
  transactions: [],
  inventory: [],
  reports: [],
});

const fallbackUser: User = { id: 0, name: "Operator", role: "Kasir", shift: "Aktif" };
const currentUser = computed(() => authUser.value ?? appData.value.users.find((user) => user.role === currentRole.value) ?? fallbackUser);
const roleProfile = computed(() => roleProfiles[currentRole.value]);
const allowedViews = computed(() => roleProfile.value.allowedViews);
const activeComponent = computed(() => ({
  pos: PosView,
  medical: MedicalRecordView,
  inventory: InventoryView,
  reports: ReportsView,
  "admin-layanan": AdminLayananView,
  "admin-produk": AdminProdukView,
  "admin-users": AdminUsersView,
  "audit-log": AuditLogView,
  dashboard: DashboardView,
  "daily-report": DailyReportView,
})[activeView.value]);
const viewProps = computed(() => ({
  token: token.value,
  patients: appData.value.patients,
  services: appData.value.services,
  therapists: appData.value.therapists,
  transactions: appData.value.transactions,
  inventory: appData.value.inventory,
  reports: appData.value.reports,
  searchQuery: searchQuery.value,
  role: currentRole.value,
  lastUpdated: lastUpdated.value,
  refreshing: refreshing.value,
  realtimeEnabled: realtimeEnabled.value,
}));

const refreshData = async ({ silent = false }: { silent?: boolean } = {}) => {
  if (!token.value) return;
  if (!silent) refreshing.value = true;
  try {
    appData.value = await getBootstrap(token.value);
    lastUpdated.value = new Date();
  } catch (error) {
    console.error("[sim-kk] bootstrap refresh gagal", error);
  } finally {
    refreshing.value = false;
  }
};

const stopRealtime = () => {
  if (refreshTimer !== null) {
    window.clearInterval(refreshTimer);
    refreshTimer = null;
  }
  realtimeEnabled.value = false;
};

const startRealtime = () => {
  stopRealtime();
  // 30s polling for roles that operate at the front desk (Kasir) or oversee
  // the rest of the clinic (Manajer / Admin). Terapis & Gudang edit isolated
  // data and don't need transaction-stream updates.
  const liveRoles: Role[] = ["Kasir", "Manajer", "Admin"];
  if (!liveRoles.includes(currentRole.value)) return;
  realtimeEnabled.value = true;
  refreshTimer = window.setInterval(() => {
    void refreshData({ silent: true });
  }, REFRESH_INTERVAL_MS);
};

const setActiveView = (view: ViewKey) => {
  if (!canOpenView(currentRole.value, view)) return;
  activeView.value = view;
  searchQuery.value = "";
};

const enterApp = async (payload: LoginPayload) => {
  loading.value = true;
  loginError.value = "";
  try {
    const session = await login(payload);
    token.value = session.token;
    try { localStorage.setItem("simkk_token", session.token); } catch { /* storage unavailable */ }
    // Backend returns `level`; mirror to `role` for UI consistency.
    const u = { ...session.user, role: (session.user as any).level ?? (session.user as any).role, name: (session.user as any).nama_lengkap ?? (session.user as any).name };
    authUser.value = u;
    currentRole.value = u.role;
    await refreshData();
    loggedIn.value = true;
    activeView.value = roleProfiles[u.role as Role].defaultView;
    searchQuery.value = "";
    startRealtime();
  } catch (error) {
    loginError.value = error instanceof Error ? error.message : "Login gagal.";
  } finally {
    loading.value = false;
  }
};

const logout = () => {
  stopRealtime();
  loggedIn.value = false;
  token.value = "";
  authUser.value = null;
  searchQuery.value = "";
  lastUpdated.value = null;
  realtimeEnabled.value = false;
  try { localStorage.removeItem("simkk_token"); } catch { /* noop */ }
};

onBeforeUnmount(() => {
  stopRealtime();
});
</script>

<template>
  <LoginView v-if="!loggedIn" :loading="loading" :error="loginError" @login="enterApp" />
  <AppShell
    v-else
    :active-view="activeView"
    :allowed-views="allowedViews"
    :role="currentRole"
    :role-scope="roleProfile.scope"
    :search-query="searchQuery"
    :user="currentUser"
    :realtime-enabled="realtimeEnabled"
    :refreshing="refreshing"
    :last-updated="lastUpdated"
    @update:view="setActiveView"
    @update:search="searchQuery = $event"
    @logout="logout"
    @manual-refresh="refreshData()"
  >
    <component :is="activeComponent" v-bind="viewProps" @refresh="refreshData()" />
  </AppShell>
</template>
