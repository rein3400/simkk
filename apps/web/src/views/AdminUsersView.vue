<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Eye, EyeOff, Pencil, Plus, Trash2, X } from "@lucide/vue";
import {
  createUser,
  deleteUser,
  listUsers,
  updateUser,
  type UserCreatePayload,
  type UserRecord,
  type UserUpdatePayload,
} from "../services/api";
import type { Role } from "../types/domain";

const props = defineProps<{ token: string; searchQuery?: string }>();

interface FormState {
  username: string;
  password: string;
  nama_lengkap: string;
  level: Role;
  shift: string;
}

const roles: Role[] = ["Kasir", "Terapis", "Gudang", "Manajer", "Admin"];

const records = ref<UserRecord[]>([]);
const loading = ref(false);
const saving = ref(false);
const formOpen = ref(false);
const editingId = ref<number | null>(null);
const showPassword = ref(false);
const confirmDelete = ref<UserRecord | null>(null);
const toastVisible = ref(false);
const toastMessage = ref("");
const toastVariant = ref<"success" | "error">("success");
const errorMessage = ref("");

const blankForm = (): FormState => ({
  username: "",
  password: "",
  nama_lengkap: "",
  level: "Kasir",
  shift: "Pagi",
});

const form = reactive<FormState>(blankForm());

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredRecords = computed(() => {
  if (!searchNeedle.value) return records.value;
  return records.value.filter((row) => [
    row.username,
    row.nama_lengkap,
    row.level,
    row.shift,
  ].some((value) => String(value).toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});

const showToast = (message: string, variant: "success" | "error" = "success") => {
  toastMessage.value = message;
  toastVariant.value = variant;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const load = async () => {
  loading.value = true;
  try {
    records.value = await listUsers(props.token);
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal memuat user.", "error");
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editingId.value = null;
  Object.assign(form, blankForm());
  showPassword.value = true;
  errorMessage.value = "";
  formOpen.value = true;
};

const openEdit = (record: UserRecord) => {
  editingId.value = record.id;
  form.username = record.username;
  form.password = "";
  form.nama_lengkap = record.nama_lengkap;
  form.level = record.level;
  form.shift = record.shift;
  showPassword.value = false;
  errorMessage.value = "";
  formOpen.value = true;
};

const closeForm = () => {
  formOpen.value = false;
  editingId.value = null;
};

const save = async () => {
  if (!form.username.trim()) {
    errorMessage.value = "Username wajib diisi.";
    return;
  }
  if (!form.nama_lengkap.trim()) {
    errorMessage.value = "Nama lengkap wajib diisi.";
    return;
  }
  if (editingId.value === null && !form.password) {
    errorMessage.value = "Password wajib untuk user baru.";
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    if (editingId.value === null) {
      const payload: UserCreatePayload = {
        username: form.username.trim(),
        password: form.password,
        nama_lengkap: form.nama_lengkap.trim(),
        level: form.level,
        shift: form.shift.trim() || "Pagi",
      };
      await createUser(props.token, payload);
      showToast("User baru tersimpan.");
    } else {
      const payload: UserUpdatePayload = {
        username: form.username.trim(),
        nama_lengkap: form.nama_lengkap.trim(),
        level: form.level,
        shift: form.shift.trim() || "Pagi",
      };
      if (form.password) payload.password = form.password;
      await updateUser(props.token, editingId.value, payload);
      showToast("User diperbarui.");
    }
    closeForm();
    await load();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Gagal menyimpan.";
  } finally {
    saving.value = false;
  }
};

const remove = async (record: UserRecord) => {
  try {
    await deleteUser(props.token, record.id);
    showToast(`User "${record.username}" dihapus.`);
    confirmDelete.value = null;
    await load();
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal menghapus.", "error");
  }
};

onMounted(() => {
  void load();
});
</script>

<template>
  <div class="admin-layout">
    <section class="admin-main">
      <div class="section-head">
        <div>
          <span>Admin · User</span>
          <h2>Akun & shift pengguna</h2>
        </div>
        <button class="primary-action" type="button" data-testid="add-user" @click="openCreate">
          <Plus :size="16" />
          Tambah user
        </button>
      </div>

      <p v-if="searchNeedle" class="search-hint">
        {{ filteredRecords.length }} user cocok dengan "{{ props.searchQuery }}".
      </p>
      <p class="search-hint">
        Tips: nama Manajer akan tercetak di laporan harian PDF. Edit user
        <strong>manajer</strong> dan ubah "Nama Lengkap" sesuai penanggung jawab
        yang sebenarnya.
      </p>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Username</th>
              <th>Nama Lengkap</th>
              <th>Level</th>
              <th>Shift</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center text-sage">Memuat...</td>
            </tr>
            <tr v-else-if="filteredRecords.length === 0">
              <td colspan="5" class="text-center text-sage">Belum ada user.</td>
            </tr>
            <tr v-for="row in filteredRecords" :key="row.id" v-else>
              <td class="font-mono text-xs">{{ row.username }}</td>
              <td><strong class="font-display italic">{{ row.nama_lengkap }}</strong></td>
              <td><span class="status-chip">{{ row.level }}</span></td>
              <td>{{ row.shift }}</td>
              <td class="text-right">
                <div class="inline-flex gap-1">
                  <button class="secondary-action" type="button" :data-testid="`edit-user-${row.id}`" @click="openEdit(row)">
                    <Pencil :size="13" />
                    Edit
                  </button>
                  <button class="secondary-action" type="button" :data-testid="`delete-user-${row.id}`" @click="confirmDelete = row">
                    <Trash2 :size="13" />
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Transition name="drawer">
      <aside v-if="formOpen" class="action-drawer" aria-modal="true">
        <header>
          <h2>{{ editingId === null ? "Tambah user" : "Edit user" }}</h2>
          <button type="button" aria-label="Tutup form" @click="closeForm">
            <X :size="18" />
          </button>
        </header>
        <div class="drawer-form">
          <label>Username<input v-model="form.username" :disabled="editingId !== null" /></label>
          <label>Nama Lengkap<input v-model="form.nama_lengkap" /></label>
          <label>Password
            <div class="relative">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" :placeholder="editingId === null ? 'Wajib diisi' : 'Kosongkan jika tidak diubah'" />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-sage" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" @click="showPassword = !showPassword">
                <EyeOff v-if="showPassword" :size="16" />
                <Eye v-else :size="16" />
              </button>
            </div>
          </label>
          <label>Level
            <select v-model="form.level">
              <option v-for="role in roles" :key="role">{{ role }}</option>
            </select>
          </label>
          <label>Shift<input v-model="form.shift" placeholder="Pagi / Siang / Malam" /></label>
          <p v-if="errorMessage" class="error-note" role="alert">{{ errorMessage }}</p>
          <div class="flex gap-2">
            <button class="primary-action" type="button" :disabled="saving" @click="save">
              {{ saving ? "Menyimpan..." : (editingId === null ? "Simpan user" : "Update user") }}
            </button>
            <button class="secondary-action" type="button" @click="closeForm">Batal</button>
          </div>
        </div>
      </aside>
    </Transition>

    <Transition name="drawer">
      <aside v-if="confirmDelete" class="action-drawer" aria-modal="true">
        <header>
          <h2>Hapus user</h2>
          <button type="button" aria-label="Tutup" @click="confirmDelete = null">
            <X :size="18" />
          </button>
        </header>
        <div class="drawer-form">
          <p>Yakin ingin menghapus user <strong>{{ confirmDelete.username }}</strong>? Akses login user ini akan dinonaktifkan.</p>
          <div class="flex gap-2">
            <button class="primary-action" type="button" :data-testid="`confirm-delete-user-${confirmDelete.id}`" @click="remove(confirmDelete)">Hapus permanen</button>
            <button class="secondary-action" type="button" @click="confirmDelete = null">Batal</button>
          </div>
        </div>
      </aside>
    </Transition>

    <Transition name="toast">
      <div v-if="toastVisible" :class="['export-toast', toastVariant === 'error' ? 'export-toast--error' : '']" role="status">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.admin-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem; align-items: start; }
.admin-main { display: flex; flex-direction: column; gap: 1rem; }
.export-toast--error { background: #fdecec; color: #7d1f1f; border-color: #f1c4c4; }
</style>
