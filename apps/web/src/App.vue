<script setup lang="ts">
import { computed, ref } from "vue";
import AppShell from "./components/AppShell.vue";
import LoginView from "./views/LoginView.vue";
import PosView from "./views/PosView.vue";
import MedicalRecordView from "./views/MedicalRecordView.vue";
import InventoryView from "./views/InventoryView.vue";
import ReportsView from "./views/ReportsView.vue";
import { getBootstrap, login, type AppData, type LoginPayload } from "./services/api";
import type { Role, User, ViewKey } from "./types/domain";
import { canOpenView, roleProfiles } from "./utils/access";

const loggedIn = ref(false);
const currentRole = ref<Role>("Kasir");
const activeView = ref<ViewKey>("pos");
const token = ref("");
const authUser = ref<User | null>(null);
const searchQuery = ref("");
const loading = ref(false);
const loginError = ref("");
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
}));

const refreshData = async () => {
  appData.value = await getBootstrap(token.value);
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
    // Backend returns `level`; mirror to `role` for UI consistency.
    const u = { ...session.user, role: (session.user as any).level ?? (session.user as any).role, name: (session.user as any).nama_lengkap ?? (session.user as any).name };
    authUser.value = u;
    currentRole.value = u.role;
    await refreshData();
    loggedIn.value = true;
    activeView.value = roleProfiles[u.role as Role].defaultView;
    searchQuery.value = "";
  } catch (error) {
    loginError.value = error instanceof Error ? error.message : "Login gagal.";
  } finally {
    loading.value = false;
  }
};

const logout = () => {
  loggedIn.value = false;
  token.value = "";
  authUser.value = null;
  searchQuery.value = "";
};
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
    @update:view="setActiveView"
    @update:search="searchQuery = $event"
    @logout="logout"
  >
    <component :is="activeComponent" v-bind="viewProps" @refresh="refreshData" />
  </AppShell>
</template>
