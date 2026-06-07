<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Pencil, Plus, Trash2, X } from "@lucide/vue";
import { createProduk, deleteProduk, listProduk, updateProduk, type ProdukRecord } from "../services/api";

const props = defineProps<{ token: string; searchQuery?: string }>();

interface FormState {
  nama: string;
  kategori: string;
  total_stok: number;
  status: "Aman" | "Menipis" | "Prioritas";
}

const records = ref<ProdukRecord[]>([]);
const loading = ref(false);
const saving = ref(false);
const formOpen = ref(false);
const editingId = ref<number | null>(null);
const confirmDelete = ref<ProdukRecord | null>(null);
const toastVisible = ref(false);
const toastMessage = ref("");
const toastVariant = ref<"success" | "error">("success");
const errorMessage = ref("");

const blankForm = (): FormState => ({
  nama: "",
  kategori: "Retail",
  total_stok: 0,
  status: "Aman",
});

const form = reactive<FormState>(blankForm());

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredRecords = computed(() => {
  if (!searchNeedle.value) return records.value;
  return records.value.filter((row) => [
    row.nama,
    row.kategori,
    row.status,
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
    records.value = await listProduk(props.token);
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal memuat produk.", "error");
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editingId.value = null;
  Object.assign(form, blankForm());
  errorMessage.value = "";
  formOpen.value = true;
};

const openEdit = (record: ProdukRecord) => {
  editingId.value = record.id;
  form.nama = record.nama;
  form.kategori = record.kategori;
  form.total_stok = record.total_stok;
  form.status = record.status;
  errorMessage.value = "";
  formOpen.value = true;
};

const closeForm = () => {
  formOpen.value = false;
  editingId.value = null;
};

const save = async () => {
  if (!form.nama.trim()) {
    errorMessage.value = "Nama produk wajib diisi.";
    return;
  }
  if (form.total_stok < 0) {
    errorMessage.value = "Stok tidak boleh negatif.";
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    const payload = {
      nama: form.nama.trim(),
      kategori: form.kategori.trim() || "Retail",
      total_stok: Number(form.total_stok),
      status: form.status,
    };
    if (editingId.value === null) {
      await createProduk(props.token, payload);
      showToast("Produk baru tersimpan.");
    } else {
      await updateProduk(props.token, editingId.value, payload);
      showToast("Produk diperbarui.");
    }
    closeForm();
    await load();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Gagal menyimpan.";
  } finally {
    saving.value = false;
  }
};

const remove = async (record: ProdukRecord) => {
  try {
    await deleteProduk(props.token, record.id);
    showToast(`Produk "${record.nama}" dihapus.`);
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
          <span>Admin · Produk</span>
          <h2>Master produk & stok</h2>
        </div>
        <button class="primary-action" type="button" data-testid="add-produk" @click="openCreate">
          <Plus :size="16" />
          Tambah produk
        </button>
      </div>

      <p v-if="searchNeedle" class="search-hint">
        {{ filteredRecords.length }} produk cocok dengan "{{ props.searchQuery }}".
      </p>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama</th>
              <th>Kategori</th>
              <th>Total Stock</th>
              <th>Status</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center text-sage">Memuat...</td>
            </tr>
            <tr v-else-if="filteredRecords.length === 0">
              <td colspan="5" class="text-center text-sage">Belum ada produk.</td>
            </tr>
            <tr v-for="row in filteredRecords" :key="row.id" v-else>
              <td><strong class="font-display italic">{{ row.nama }}</strong></td>
              <td>{{ row.kategori }}</td>
              <td class="font-mono text-xs">{{ row.total_stok }}</td>
              <td>
                <span :class="['status-chip', row.status.toLowerCase() === 'aman' ? 'aman' : row.status.toLowerCase() === 'menipis' ? 'menipis' : 'low']">
                  {{ row.status }}
                </span>
              </td>
              <td class="text-right">
                <div class="inline-flex gap-1">
                  <button class="secondary-action" type="button" :data-testid="`edit-produk-${row.id}`" @click="openEdit(row)">
                    <Pencil :size="13" />
                    Edit
                  </button>
                  <button class="secondary-action" type="button" :data-testid="`delete-produk-${row.id}`" @click="confirmDelete = row">
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
          <h2>{{ editingId === null ? "Tambah produk" : "Edit produk" }}</h2>
          <button type="button" aria-label="Tutup form" @click="closeForm">
            <X :size="18" />
          </button>
        </header>
        <div class="drawer-form">
          <label>Nama<input v-model="form.nama" placeholder="Contoh: Sunscreen SPF50" /></label>
          <label>Kategori<input v-model="form.kategori" placeholder="Retail / Treatment / dll" /></label>
          <label>Total Stok<input v-model.number="form.total_stok" type="number" min="0" /></label>
          <label>Status
            <select v-model="form.status">
              <option>Aman</option>
              <option>Menipis</option>
              <option>Prioritas</option>
            </select>
          </label>
          <p v-if="errorMessage" class="error-note" role="alert">{{ errorMessage }}</p>
          <div class="flex gap-2">
            <button class="primary-action" type="button" :disabled="saving" @click="save">
              {{ saving ? "Menyimpan..." : (editingId === null ? "Simpan produk" : "Update produk") }}
            </button>
            <button class="secondary-action" type="button" @click="closeForm">Batal</button>
          </div>
        </div>
      </aside>
    </Transition>

    <Transition name="drawer">
      <aside v-if="confirmDelete" class="action-drawer" aria-modal="true">
        <header>
          <h2>Hapus produk</h2>
          <button type="button" aria-label="Tutup" @click="confirmDelete = null">
            <X :size="18" />
          </button>
        </header>
        <div class="drawer-form">
          <p>Yakin ingin menghapus <strong>{{ confirmDelete.nama }}</strong>?</p>
          <div class="flex gap-2">
            <button class="primary-action" type="button" :data-testid="`confirm-delete-produk-${confirmDelete.id}`" @click="remove(confirmDelete)">Hapus permanen</button>
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
